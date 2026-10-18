<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\RouteUpdated;
use App\Models\RouteStop;
use App\Models\Tenant;

/**
 * Put a skipped stop back: due again when its day is today or later, missed
 * when its day has already gone. The live schedule board refreshes.
 */
class UnskipStop
{
    /** @return bool false when the stop isn't skipped (nothing changed) */
    public function handle(RouteStop $stop): bool
    {
        $route = $stop->route;
        if ($stop->status !== 'skipped' || $route === null) {
            return false;
        }

        $past = $route->scheduled_date->toDateString() < Tenant::localToday()->toDateString();
        $stop->update(['status' => $past ? 'missed' : 'pending', 'skip_reason' => null]);
        event(RouteUpdated::for($route));

        return true;
    }
}
