<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\ManualCharge;
use App\Models\Pool;
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
            ->whereNull('paid_at')
            ->sum('amount');

        return round($service + $charges, 2);
    }

    private function visitPrice(ServiceVisit $visit): float
    {
        $sub = $visit->pool?->subscriptions->firstWhere('status', 'active');

        return $sub instanceof ServiceSubscription ? (float) $sub->serviceType->price : 0.0;
    }

    /**
     * Outstanding balances for many customers at once — page-bounded, ~4 queries
     * total (no per-customer N+1). Mirrors outstandingForCustomer's math.
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

        // pool_id => customer_id (tenant-scoped; soft-deletes excluded)
        $poolCustomer = Pool::query()->whereIn('customer_id', $customerIds)->pluck('customer_id', 'id');
        $poolIds = $poolCustomer->keys()->all();

        if ($poolIds !== []) {
            // pool_id => first active subscription's serviceType price
            $price = [];
            ServiceSubscription::query()->whereIn('pool_id', $poolIds)->where('status', 'active')
                ->with('serviceType:id,price')->get()
                ->each(function (ServiceSubscription $s) use (&$price): void {
                    $pid = (int) $s->getAttribute('pool_id');
                    if (! isset($price[$pid])) {
                        $price[$pid] = (float) $s->serviceType->price;
                    }
                });

            // pool_id => count of unpaid completed visits
            $counts = ServiceVisit::query()->whereIn('pool_id', $poolIds)
                ->where('status', 'completed')->whereNull('paid_at')
                ->selectRaw('pool_id, count(*) as c')->groupBy('pool_id')->pluck('c', 'pool_id');

            foreach ($poolCustomer as $poolId => $customerId) {
                $owed = (float) ($counts[$poolId] ?? 0) * ($price[(int) $poolId] ?? 0.0);
                $balances[(int) $customerId] += $owed;
            }
        }

        ManualCharge::query()->whereIn('customer_id', $customerIds)->whereNull('paid_at')
            ->selectRaw('customer_id, sum(amount) as s')->groupBy('customer_id')->pluck('s', 'customer_id')
            ->each(function ($sum, $customerId) use (&$balances): void {
                $balances[(int) $customerId] += (float) $sum;
            });

        return array_map(fn (float $v): float => round($v, 2), $balances);
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
            ->whereNull('paid_at')
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
        $customers = Customer::query()->get();
        $balances = $this->balancesFor($customers->pluck('id')->all());

        return $customers
            ->map(fn (Customer $c): array => ['customer' => $c, 'balance' => $balances[$c->id] ?? 0.0])
            ->filter(fn (array $row): bool => $row['balance'] > 0)
            ->sortByDesc('balance')
            ->values();
    }
}
