<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\ManualCharge;
use App\Models\ServiceSubscription;
use App\Models\ServiceVisit;
use Illuminate\Support\Collection;

/**
 * Customer accounts-receivable. A customer's outstanding balance is the sum
 * of completed-but-unpaid service visits (priced from the pool's active
 * subscription) plus any uninvoiced manual charges. Invoicing/tax/Stripe
 * settlement layer on top of these records; this is the live AR view the
 * Balances screen and the lookup_balance tool read.
 */
class BillingService
{
    /** Total currently owed by a customer (pre-tax). */
    public function outstandingForCustomer(Customer $customer): float
    {
        $visits = ServiceVisit::query()
            ->whereIn('pool_id', $customer->pools()->pluck('id'))
            ->where('status', 'completed')
            ->whereNull('paid_at')
            ->with('pool.subscriptions.serviceType')
            ->get();

        $service = (float) $visits->sum(fn (ServiceVisit $v): float => $this->visitPrice($v));
        $charges = (float) ManualCharge::query()
            ->where('customer_id', $customer->id)
            ->whereNull('invoice_id')
            ->sum('amount');

        return round($service + $charges, 2);
    }

    private function visitPrice(ServiceVisit $visit): float
    {
        $sub = $visit->pool?->subscriptions->firstWhere('status', 'active');

        return $sub instanceof ServiceSubscription ? (float) $sub->serviceType->price : 0.0;
    }

    /**
     * The line-by-line AR breakdown behind a customer's balance.
     *
     * @return array{visits: list<array{pool: string, date: string, price: float}>, charges: list<array{description: string, amount: float}>, total: float}
     */
    public function breakdownForCustomer(Customer $customer): array
    {
        $visits = ServiceVisit::query()
            ->whereIn('pool_id', $customer->pools()->pluck('id'))
            ->where('status', 'completed')
            ->whereNull('paid_at')
            ->with('pool.subscriptions.serviceType')
            ->latest('completed_at')
            ->get()
            ->map(fn (ServiceVisit $v): array => [
                'pool' => $v->pool->name,
                'date' => $v->completed_at?->toDateString() ?? '',
                'price' => $this->visitPrice($v),
            ])->all();

        $charges = ManualCharge::query()
            ->where('customer_id', $customer->id)
            ->whereNull('invoice_id')
            ->get()
            ->map(fn (ManualCharge $m): array => ['description' => $m->description, 'amount' => (float) $m->amount])
            ->all();

        $total = round(array_sum(array_column($visits, 'price')) + array_sum(array_column($charges, 'amount')), 2);

        return ['visits' => $visits, 'charges' => $charges, 'total' => $total];
    }

    /**
     * Every customer with a positive balance, highest first.
     *
     * @return Collection<int, array{customer: Customer, balance: float}>
     */
    public function outstandingBalances(): Collection
    {
        return Customer::query()->with('pools')->get()
            ->map(fn (Customer $c): array => ['customer' => $c, 'balance' => $this->outstandingForCustomer($c)])
            ->filter(fn (array $row): bool => $row['balance'] > 0)
            ->sortByDesc('balance')
            ->values();
    }
}
