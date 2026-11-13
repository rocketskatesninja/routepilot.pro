<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\RouteUpdated;
use App\Models\RouteStop;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * The tech opened a stop on today's route in the field app: the office's
 * schedule shows it "In progress" until it's finished. A tech is at one pool at
 * a time, so a stop they opened before and left unfinished goes back to pending.
 * Finishing, skipping or the nightly missed-stop run move it on from there.
 */
class StartVisit
{
    /** @return bool false when nothing changed (not today's, or not waiting to be done) */
    public function handle(RouteStop $stop): bool
    {
        $route = $stop->route;
        if ($route === null || $stop->status !== 'pending' || $route->scheduled_date->toDateString() !== Tenant::localToday()->toDateString()) {
            return false;
        }

        DB::transaction(function () use ($stop, $route): void {
            RouteStop::query()->where('route_id', $route->id)->where('status', 'in_progress')->update(['status' => 'pending']);
            $stop->update(['status' => 'in_progress']);
        });
        event(RouteUpdated::for($route));

        return true;
    }
}
