<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceVisit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Delete a plan that was added by mistake. A plan that has done work (any
 * completed visit) can't be deleted — it's ended instead, keeping its history.
 * One that can be deleted never really happened, so every stop it put on the
 * schedule goes with it, past ones included — otherwise they'd linger as
 * "missed" stops with no plan.
 */
class DeleteSubscription
{
    /** False when the plan has completed visits and must be ended instead. */
    public function handle(ServiceSubscription $subscription, User $by): bool
    {
        $hasHistory = ServiceVisit::query()->where('service_subscription_id', $subscription->id)->where('status', 'completed')->exists()
            || RouteStop::query()->where('service_subscription_id', $subscription->id)->where('status', 'completed')->exists();
        if ($hasHistory) {
            return false;
        }

        DB::transaction(function () use ($subscription, $by): void {
            $stops = RouteStop::query()->where('service_subscription_id', $subscription->id)->get(['id', 'route_id']);
            RouteStop::query()->whereKey($stops->pluck('id'))->delete();
            Route::query()->whereKey($stops->pluck('route_id')->unique())->whereDoesntHave('stops')->delete();
            AuditLog::record($by, 'subscription.deleted', $subscription, ['stops_removed' => $stops->count()]);
            $subscription->delete();
        });

        return true;
    }
}
