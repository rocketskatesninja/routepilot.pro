<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\RouteUpdated;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Add a pool that isn't on a tech's route to the end of their route for today,
 * as an extra visit: a stop with no plan, so finishing it files a report like
 * any other visit but makes no charge. Adding a pool that's already waiting on
 * the route just returns that stop.
 */
class AddExtraStop
{
    public function handle(Pool $pool, User $tech, ?string $date = null): RouteStop
    {
        // Today, unless the office is filling in a tech's earlier day.
        $route = Route::forDay((int) $tech->tenant_id, $tech->id, $date ?? Tenant::localToday()->toDateString());

        $stop = DB::transaction(function () use ($route, $pool): RouteStop {
            // Lock the route so two taps can't both add the pool or take the same place in line.
            Route::query()->whereKey($route->id)->lockForUpdate()->first();

            $waiting = RouteStop::query()
                ->where('route_id', $route->id)
                ->where('pool_id', $pool->id)
                ->whereIn('status', ['pending', 'in_progress'])
                ->first();
            if ($waiting !== null) {
                return $waiting;
            }

            return RouteStop::query()->create([
                'route_id' => $route->id,
                'pool_id' => $pool->id,
                'service_subscription_id' => null,
                'stop_order' => (int) RouteStop::query()->where('route_id', $route->id)->max('stop_order') + 1,
                'status' => 'pending',
            ]);
        });

        event(RouteUpdated::for($route));

        return $stop;
    }
}
