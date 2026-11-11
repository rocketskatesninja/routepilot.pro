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
        $extra = $this->extra($tenant);
        $price = (string) config('billing.prices.pool');
        $billed = $item !== null ? (int) $item->getAttribute('quantity') : 0;
        $oldPrice = $item !== null ? $this->priceOf($item) : $price;
        $step = $this->step($item !== null, $billed, $extra, $oldPrice !== $price);
        if ($step === 'none') {
            return $extra;
        }

        $subscription->noProrate();
        if ($step === 'move') {
            // The extra-pool rate changed: move the line onto the current price
            // (added before the old one comes off, so the plan is never left bare).
            if ($extra > 0) {
                $subscription->addPrice($price, $extra);
            }
            $subscription->removePrice($oldPrice);
        } else {
            match ($step) {
                'add' => $subscription->addPrice($price, $extra),
                'remove' => $subscription->removePrice($price),
                default => $subscription->updateQuantity($extra, $price),
            };
        }

        // A scheduled job has no company of its own, so the entry is filed under this one.
        $changes = ['from' => $billed, 'to' => $extra] + ($step === 'move' ? ['old_price' => $oldPrice, 'new_price' => $price] : []);
        AuditLog::record(null, 'billing.extra_pools', $tenant, $changes)->forceFill(['tenant_id' => $tenant->id])->save();

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

    /**
     * What to do with the extra-pool line: nothing, add it, change its count,
     * remove it, or move it onto a new price.
     *
     * @return 'none'|'add'|'update'|'remove'|'move'
     */
    private function step(bool $hasLine, int $billed, int $extra, bool $oldPrice): string
    {
        return match (true) {
            $hasLine && $oldPrice => 'move',
            $billed === $extra => 'none',
            ! $hasLine => 'add',
            $extra === 0 => 'remove',
            default => 'update',
        };
    }

    private function priceOf(SubscriptionItem $item): string
    {
        return (string) $item->getAttribute('stripe_price');
    }

    private function poolItem(Subscription $subscription): ?SubscriptionItem
    {
        // The plan is the base price plus at most this one line, so any other
        // line is it, even on an older extra-pool price.
        return SubscriptionItem::query()
            ->where('subscription_id', $subscription->getKey())
            ->where('stripe_price', '!=', (string) config('billing.prices.base'))
            ->first();
    }
}
