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

/**
 * The customer billing ledger — the one place money rules live.
 *
 *  - A customer's balance is their charges (amount + tax, not voided) minus
 *    their successful payments. Nothing is re-derived from plans or prices.
 *  - A completed visit is charged once, at its own plan's price at that moment.
 *  - Tax is worked out per charge, when the charge is created.
 *  - Payments made against an invoice pay that invoice first; everything else
 *    (and any credits) pays the oldest charges first. Invoice paid/overdue
 *    status always follows from that.
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
     * What makes up a customer's balance: the unpaid part of each charge.
     *
     * @return array{visits: list<array{pool: string, date: string, price: float}>, charges: list<array{description: string, amount: float}>, total: float}
     */
    public function breakdownForCustomer(Customer $customer): array
    {
        $unpaid = $this->unpaid($customer);
        $open = Charge::query()->whereKey(array_keys(array_filter($unpaid, fn (float $left): bool => $left > 0)))
            ->with('pool:id,name')->orderByDesc('occurred_on')->orderByDesc('id')->get();

        $visits = $open->where('kind', 'visit')->map(fn (Charge $c): array => [
            'description' => $c->description, // "Weekly Pool Service — Anderson Pool"
            'pool' => $c->pool?->getAttribute('name') ?? $c->description,
            'date' => $c->occurred_on->toDateString(),
            'price' => $unpaid[$c->id],
        ])->values()->all();
        $charges = $open->where('kind', '!=', 'visit')->map(fn (Charge $c): array => [
            'description' => $c->description,
            'amount' => $unpaid[$c->id],
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
            $status = match (true) {
                $total > 0 && $left <= 0 => 'paid',
                $invoice->due_at !== null && $invoice->due_at->toDateString() < $today => 'overdue',
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
     * The unpaid part of each of a customer's charges. Payments (and credits)
     * made against an invoice pay that invoice first; everything else pays the
     * oldest charges first.
     *
     * @return array<int, float> charge id => unpaid amount
     */
    private function unpaid(Customer $customer): array
    {
        $charges = Charge::query()->active()->where('customer_id', $customer->id)
            ->orderBy('occurred_on')->orderBy('id')->get();
        $payments = Payment::query()->where('customer_id', $customer->id)->where('status', 'succeeded')->get(['amount', 'invoice_id']);

        $left = [];
        $credits = $charges->filter(fn (Charge $c): bool => $c->total() < 0);
        foreach ($charges as $c) {
            $left[$c->id] = max(0.0, $c->total());
        }

        $pay = function (Collection $targets, float $money) use (&$left): float {
            foreach ($targets as $c) {
                if ($money <= 0) {
                    break;
                }
                $take = min($left[$c->id], $money);
                $left[$c->id] = round($left[$c->id] - $take, 2);
                $money = round($money - $take, 2);
            }

            return $money;
        };

        $general = 0.0;
        $invoiceIds = $payments->pluck('invoice_id')->merge($credits->pluck('invoice_id'))->filter()->unique();
        foreach ($invoiceIds as $invoiceId) {
            $money = (float) $payments->where('invoice_id', $invoiceId)->sum('amount')
                - (float) $credits->where('invoice_id', $invoiceId)->sum(fn (Charge $c): float => $c->total());
            $general += $pay($charges->where('invoice_id', $invoiceId), $money);
        }
        $general += (float) $payments->whereNull('invoice_id')->sum('amount')
            - (float) $credits->whereNull('invoice_id')->sum(fn (Charge $c): float => $c->total());
        $pay($charges, $general);

        return $left;
    }

    /** The visit's date in the company's own time zone. */
    private function localDate(ServiceVisit $visit): string
    {
        $tz = (string) ($visit->tenant?->getAttribute('timezone') ?: config('app.timezone'));

        return ($visit->completed_at ?? now())->copy()->setTimezone($tz)->toDateString();
    }
}
