<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\RouteUpdated;
use App\Models\Route;
use App\Models\RouteStop;

/**
 * Take an extra visit back off a route before it's done (the tech picked the
 * wrong pool, or didn't get to it), so it doesn't turn into a missed stop
 * overnight. A route left with no stops goes with it.
 */
class RemoveExtraStop
{
    /** @return bool false when the stop isn't an extra visit that's still waiting (nothing changed) */
    public function handle(RouteStop $stop): bool
    {
        $route = $stop->route;
        if ($route === null || $stop->service_subscription_id !== null || $stop->status !== 'pending' || $stop->serviceVisit()->exists()) {
            return false;
        }

        $stop->delete();
        Route::query()->whereKey($route->id)->whereDoesntHave('stops')->delete();
        event(RouteUpdated::for($route));

        return true;
    }
}
