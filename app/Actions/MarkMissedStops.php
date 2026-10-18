<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\RouteStop;
use App\Models\Tenant;

/**
 * Close out the bound company's past days: a stop still pending (or left under
 * way) after its day becomes "missed", so a week nobody serviced shows up on the
 * schedule, in the pool's panel and in the morning alert, instead of looking
 * like it's still planned. Run nightly by DailyOpsAlerts (tenant bound) just
 * before the checks. A missed stop can still be finished later (a late offline
 * sync completes it).
 */
class MarkMissedStops
{
    /** @return int how many stops were marked missed */
    public function handle(): int
    {
        return RouteStop::query()
            ->whereIn('status', ['pending', 'in_progress'])
            // RouteStop isn't tenant-scoped; its Route is, so this keeps to the bound company.
            ->whereHas('route', fn ($q) => $q->whereDate('scheduled_date', '<', Tenant::localToday()))
            ->update(['status' => 'missed']);
    }
}
