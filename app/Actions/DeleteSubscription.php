<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\User;
use App\Services\SubscriptionMaterializer;
use Illuminate\Support\Facades\DB;

/**
 * Delete a plan that was added by mistake. A plan that has done work (any
 * completed visit) can't be deleted — it's ended instead, keeping its history.
 */
class DeleteSubscription
{
    public function __construct(private readonly SubscriptionMaterializer $scheduler) {}

    /** False when the plan has completed visits and must be ended instead. */
    public function handle(ServiceSubscription $subscription, User $by): bool
    {
        $hasHistory = RouteStop::query()
            ->where('service_subscription_id', $subscription->id)
            ->where('status', 'completed')
            ->exists();
        if ($hasHistory) {
            return false;
        }

        DB::transaction(function () use ($subscription, $by): void {
            $this->scheduler->clearUpcoming($subscription);
            AuditLog::record($by, 'subscription.deleted', $subscription);
            $subscription->delete();
        });

        return true;
    }
}
