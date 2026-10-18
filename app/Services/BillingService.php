<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Charge;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The customer billing ledger — the one place money rules live.
 *
 *  - A customer's balance is their charges (amount + tax, not voided) minus
 *    their successful payments. Nothing is re-derived from plans or prices.
 *  - A completed visit is charged once, at its own plan's price at that moment.
 *  - Tax is worked out per charge, when the charge is created.
 *  - Payments made against an invoice pay that invoice first; everything else
 *    (and any credits) pays the oldest charges first. Invoice paid/overdue
 *    status always follows from that, here in refreshInvoices() and nowhere
 *    else.
 */
class BillingService
{
    /** What a customer owes right now (negative = they're in credit). */
    public function outstandingForCustomer(Customer $customer): float
    {
        return $this->balancesFor([$customer->id])[$customer->id] ?? 0.0;
    }

    /**
     * Balances for many customers at once — two grouped queries.
     *
     * @param  list<int>  $customerIds
     * @return array<int, float> customer_id => balance
     */
    public function balancesFor(array $customerIds): array
    {
        $balances = array_fill_keys($customerIds, 0.0);
        if ($customerIds === []) {
            return $balances;
        }

        Charge::query()->active()->whereIn('customer_id', $customerIds)
            ->selectRaw('customer_id, sum(amount + tax) as owed')->groupBy('customer_id')->pluck('owed', 'customer_id')
            ->each(function ($owed, $id) use (&$balances): void {
                $balances[(int) $id] += (float) $owed;
            });

        Payment::query()->where('status', 'succeeded')->whereIn('customer_id', $customerIds)
            ->selectRaw('customer_id, sum(amount) as paid')->groupBy('customer_id')->pluck('paid', 'customer_id')
            ->each(function ($paid, $id) use (&$balances): void {
                $balances[(int) $id] -= (float) $paid;
            });

        return array_map(fn (float $v): float => round($v, 2), $balances);
    }

    /**
     * Every customer who owes something, highest first.
     *
     * @return Collection<int, array{customer: Customer, balance: float}>
     */
    public function outstandingBalances(): Collection
    {
        $customers = Customer::query()->get();
        $balances = $this->balancesFor($customers->pluck('id')->all());

        return $customers
            ->map(fn (Customer $c): array => ['customer' => $c, 'balance' => $balances[$c->id] ?? 0.0])
            ->filter(fn (array $row): bool => $row['balance'] > 0)
            ->sortByDesc('balance')
            ->values();
    }

    /**
     * What makes up a customer's balance: the unpaid part of each charge (and
     * what the charge was, for one that's partly paid).
     *
     * @return array{visits: list<array{service: string, pool: string, date: string, price: float, charged: float}>, charges: list<array{description: string, amount: float, charged: float}>, total: float}
     */
    public function breakdownForCustomer(Customer $customer): array
    {
        $unpaid = $this->unpaid($customer);
        $open = Charge::query()->whereKey(array_keys(array_filter($unpaid, fn (float $left): bool => $left > 0)))
            ->with('pool:id,name')->orderByDesc('occurred_on')->orderByDesc('id')->get();

        $visits = $open->where('kind', 'visit')->map(fn (Charge $c): array => [
            'service' => Str::before($c->description, ' — '), // "Weekly Pool Service — Anderson Pool"
            'pool' => $c->pool?->getAttribute('name') ?? $c->description,
            'date' => $c->occurred_on->toDateString(),
            'price' => $unpaid[$c->id],
            'charged' => $c->total(),
        ])->values()->all();
        $charges = $open->where('kind', '!=', 'visit')->map(fn (Charge $c): array => [
            'description' => $c->description,
            'amount' => $unpaid[$c->id],
            'charged' => $c->total(),
        ])->values()->all();

        return ['visits' => $visits, 'charges' => $charges, 'total' => $this->outstandingForCustomer($customer)];
    }

    /** Charge a completed visit at its own plan's price — once. No plan, no charge. */
    public function chargeVisit(ServiceVisit $visit): ?Charge
    {
        $existing = Charge::query()->where('service_visit_id', $visit->id)->first();
        $plan = $visit->serviceSubscription()->with('serviceType:id,name,price')->first();
        $pool = $visit->pool()->first(['id', 'name', 'customer_id']);
        if ($existing !== null || $plan === null || $pool === null) {
            return $existing;
        }

        $charge = new Charge([
            'customer_id' => $pool->getAttribute('customer_id'),
            'pool_id' => $pool->id,
            'service_visit_id' => $visit->id,
            'kind' => 'visit',
            'description' => $plan->serviceType->name.' — '.$pool->getAttribute('name'),
            'amount' => (float) $plan->serviceType->price,
            'taxable' => false,
            'tax' => 0,
            'occurred_on' => $this->localDate($visit),
        ]);
        $charge->forceFill(['tenant_id' => $visit->getAttribute('tenant_id')])->save();

        return $charge;
    }

    /** Add a one-off charge (or a credit, with a negative amount), taxed at today's rate. */
    public function addCharge(Customer $customer, string $kind, string $description, float $amount, bool $taxable, ?string $occurredOn, ?int $userId, ?int $poolId = null): Charge
    {
        $rate = (float) ($customer->tenant?->getAttribute('tax_rate') ?? 0);

        $charge = new Charge([
            'customer_id' => $customer->id,
            'pool_id' => $poolId,
            'kind' => $kind,
            'description' => $description,
            'amount' => round($amount, 2),
            'taxable' => $taxable,
            'tax' => $taxable ? round($amount * $rate, 2) : 0,
            'occurred_on' => $occurredOn ?? ($customer->tenant?->today() ?? Tenant::localToday())->toDateString(),
            'created_by' => $userId,
        ]);
        $charge->forceFill(['tenant_id' => $customer->getAttribute('tenant_id')])->save();

        return $charge;
    }

    /** Bring a customer's invoices (totals, amount paid, paid/overdue) in line with the ledger. */
    public function refreshInvoices(Customer $customer): void
    {
        $unpaid = $this->unpaid($customer);
        $today = ($customer->tenant?->today() ?? Tenant::localToday())->toDateString();

        $invoices = Invoice::query()->where('customer_id', $customer->id)->where('status', '!=', 'void')
            ->with(['charges' => fn ($q) => $q->active()])->get();

        foreach ($invoices as $invoice) {
            $total = round((float) $invoice->charges->sum(fn (Charge $c): float => $c->total()), 2);
            $left = round((float) $invoice->charges->sum(fn (Charge $c): float => max(0.0, $unpaid[$c->id] ?? 0.0)), 2);
            // Overdue is about money still owed: an invoice with nothing left on
            // it is never overdue, however old it is.
            $status = match (true) {
                $total > 0 && $left <= 0 => 'paid',
                $left > 0 && $invoice->due_at !== null && $invoice->due_at->toDateString() < $today => 'overdue',
                $invoice->status === 'draft' => 'draft',
                default => 'sent',
            };

            $invoice->update([
                'subtotal' => round((float) $invoice->charges->sum('amount'), 2),
                'tax' => round((float) $invoice->charges->sum('tax'), 2),
                'total' => $total,
                'amount_paid' => round($total - $left, 2),
                'status' => $status,
            ]);
        }
    }

    /**
     * What paid an invoice: each payment (or credit) that went to it, newest
     * first, and how much of it did — a payment on account can be split across
     * several invoices.
     *
     * @return list<array{source: Payment|Charge, applied: float}>
     */
    public function paidBy(Invoice $invoice): array
    {
        $applied = $this->allocate((int) $invoice->getAttribute('customer_id'))['applied'][$invoice->id] ?? [];
        $payments = Payment::query()->with('recorder:id,first_name,last_name')->whereKey(array_keys($applied['payment'] ?? []))->get();
        $credits = Charge::query()->whereKey(array_keys($applied['credit'] ?? []))->get();

        return $payments->map(fn (Payment $p): array => ['source' => $p, 'applied' => $applied['payment'][$p->id], 'on' => $p->paid_at?->getTimestamp() ?? 0])
            ->concat($credits->map(fn (Charge $c): array => ['source' => $c, 'applied' => $applied['credit'][$c->id], 'on' => $c->occurred_on->getTimestamp()]))
            ->sortByDesc('on')
            ->map(fn (array $row): array => ['source' => $row['source'], 'applied' => $row['applied']])
            ->values()->all();
    }

    /**
     * The unpaid part of each of a customer's charges.
     *
     * @return array<int, float> charge id => unpaid amount
     */
    private function unpaid(Customer $customer): array
    {
        return $this->allocate($customer->id)['unpaid'];
    }

    /**
     * Put a customer's money (payments, and credits) against their charges.
     * Money made against an invoice pays that invoice first; everything else,
     * and whatever is left over, pays the oldest charges first. Returns what's
     * still owed on each charge, and what paid each invoice (by payment/credit id).
     *
     * @return array{unpaid: array<int, float>, applied: array<int, array{payment?: array<int, float>, credit?: array<int, float>}>}
     */
    private function allocate(int $customerId): array
    {
        $charges = Charge::query()->active()->where('customer_id', $customerId)
            ->orderBy('occurred_on')->orderBy('id')->get();
        $unpaid = [];
        foreach ($charges as $c) {
            $unpaid[$c->id] = max(0.0, $c->total());
        }
        $applied = [];

        // The money, oldest first: payments, then credits (negative charges).
        $sources = Payment::query()->where('customer_id', $customerId)->where('status', 'succeeded')
            ->orderBy('paid_at')->orderBy('id')->get(['id', 'amount', 'invoice_id'])
            ->map(fn (Payment $p): array => ['kind' => 'payment', 'id' => $p->id, 'invoice_id' => $p->invoice_id, 'amount' => (float) $p->amount])
            ->concat($charges->filter(fn (Charge $c): bool => $c->total() < 0)
                ->map(fn (Charge $c): array => ['kind' => 'credit', 'id' => $c->id, 'invoice_id' => $c->invoice_id, 'amount' => -$c->total()]));

        // Pay down the targets in order; returns what's left of the money.
        $pay = function (Collection $targets, array $source) use (&$unpaid, &$applied): float {
            $money = $source['amount'];
            foreach ($targets as $c) {
                if ($money <= 0) {
                    break;
                }
                $take = min($unpaid[$c->id], $money);
                if ($take <= 0) {
                    continue;
                }
                $unpaid[$c->id] = round($unpaid[$c->id] - $take, 2);
                $money = round($money - $take, 2);
                if ($c->invoice_id !== null) {
                    $applied[$c->invoice_id][$source['kind']][$source['id']] = round(($applied[$c->invoice_id][$source['kind']][$source['id']] ?? 0.0) + $take, 2);
                }
            }

            return $money;
        };

        $rest = [];
        foreach ($sources as $source) {
            if ($source['invoice_id'] !== null) {
                $source['amount'] = $pay($charges->where('invoice_id', $source['invoice_id']), $source);
            }
            $rest[] = $source;
        }
        foreach ($rest as $source) {
            $pay($charges, $source);
        }

        return ['unpaid' => $unpaid, 'applied' => $applied];
    }

    /** The visit's date in the company's own time zone. */
    private function localDate(ServiceVisit $visit): string
    {
        $tz = $visit->tenant?->timezoneName() ?? (string) config('app.timezone');

        return ($visit->completed_at ?? now())->copy()->setTimezone($tz)->toDateString();
    }
}
