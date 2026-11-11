<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Services\BillingMeter;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;

/**
 * Keep a company's subscription billing the pools it has beyond the plan's
 * allowance: the per-pool price, at a quantity of its extra pools (added when
 * it first goes over, removed when it's back within the allowance).
 *
 * Changes never prorate. A company is billed, each renewal, for the extra pools
 * it has that day: one added mid-month is first charged at the next renewal.
 */
class SyncExtraPoolBilling
{
    public function __construct(private readonly BillingMeter $meter) {}

    /**
     * What it's billed for now and what it should be, or null when there's no
     * subscription to keep in step (a trial, a comped company, one that ended).
     *
     * @return array{billed: int, extra: int}|null
     */
    public function counts(Tenant $tenant): ?array
    {
        $subscription = $this->subscription($tenant);

        return $subscription === null ? null : [
            'billed' => (int) ($this->poolItem($subscription)->quantity ?? 0),
            'extra' => $this->extra($tenant),
        ];
    }

    /** Bring the subscription in step; returns the extra pools it's billed for, or null (nothing to keep in step). */
    public function handle(Tenant $tenant): ?int
    {
        $subscription = $this->subscription($tenant);
        if ($subscription === null) {
            return null;
        }
        $item = $this->poolItem($subscription);
        $billed = (int) ($item->quantity ?? 0);
        $extra = $this->extra($tenant);
        if ($billed === $extra) {
            return $extra;
        }

        $price = (string) config('billing.prices.pool');
        $subscription->noProrate();
        match (true) {
            $item === null => $subscription->addPrice($price, $extra),
            $extra === 0 => $subscription->removePrice($price),
            default => $subscription->updateQuantity($extra, $price),
        };

        // A scheduled job has no company of its own, so the entry is filed under this one.
        AuditLog::record(null, 'billing.extra_pools', $tenant, ['from' => $billed, 'to' => $extra])
            ->forceFill(['tenant_id' => $tenant->id])->save();

        return $extra;
    }

    private function subscription(Tenant $tenant): ?Subscription
    {
        $subscription = $tenant->subscription('default');

        return filled(config('billing.prices.pool')) && $subscription !== null && ! $subscription->ended() && ! $subscription->incomplete()
            ? $subscription
            : null;
    }

    private function extra(Tenant $tenant): int
    {
        return (int) $this->meter->for($tenant)['pools']['over'];
    }

    private function poolItem(Subscription $subscription): ?SubscriptionItem
    {
        $item = $subscription->items->firstWhere('stripe_price', (string) config('billing.prices.pool'));

        return $item instanceof SubscriptionItem ? $item : null;
    }
}
