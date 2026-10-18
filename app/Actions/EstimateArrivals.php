<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Route;
use App\Support\Geo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Compute each stop's `estimated_arrival` for a route. Walk the stops in order
 * from the route's start time, accumulating a drive-time estimate (haversine ×
 * road factor ÷ average speed, floored) between consecutive located stops plus
 * each stop's on-site service duration. Arrivals are real (UTC) moments: the
 * start time is the company's local clock on the route's date, and screens show
 * them back on that clock (Tenant::localTime, EtaWindow).
 *
 * A local, zero-cost model — driveMinutes() is the one seam a real drive-time
 * API would replace later. Stops without coordinates get a null ETA and don't
 * advance the clock (they can't be placed in time).
 */
class EstimateArrivals
{
    /**
     * Compute estimated_arrival for a route's stops.
     *
     * Plan-time (no args): time every stop from HQ + the route's start time.
     * Live re-anchor ($fromPoint = agent GPS, $fromTime = now): re-time only the
     * still-pending tail from where the agent actually is — finished stops keep
     * their recorded arrival.
     *
     * @param  array{0: float, 1: float}|null  $fromPoint
     */
    public function handle(Route $route, ?array $fromPoint = null, ?Carbon $fromTime = null): void
    {
        $stops = $route->stops()
            ->with(['pool.serviceLocation', 'serviceSubscription.serviceType'])
            ->orderBy('stop_order')
            ->get();

        if ($stops->isEmpty()) {
            return;
        }

        $live = $fromPoint !== null && $fromTime !== null;
        $start = $fromTime ?? $this->startTime($route);
        $prev = $live ? $fromPoint : $this->startPoint($route);
        $offset = 0;

        $roadFactor = (float) config('routing.road_factor');
        $speed = max(1.0, (float) config('routing.avg_speed_kmh'));
        $minDrive = (int) config('routing.min_drive_minutes');
        $defaultService = (int) config('routing.default_service_minutes');

        DB::transaction(function () use ($stops, $start, $live, &$prev, &$offset, $roadFactor, $speed, $minDrive, $defaultService): void {
            foreach ($stops as $stop) {
                // A live re-anchor re-times only what's still ahead; the rest is history.
                if ($live && $stop->getAttribute('status') !== 'pending') {
                    continue;
                }

                $coord = $stop->pool?->coordinates();
                if ($coord === null) {
                    $stop->update(['estimated_arrival' => null]);

                    continue;
                }

                $offset += $prev === null ? 0 : $this->driveMinutes($prev, $coord, $roadFactor, $speed, $minDrive);
                $stop->update(['estimated_arrival' => $start->copy()->addMinutes($offset)]);

                $offset += (int) ($stop->serviceSubscription?->serviceType->estimated_duration_minutes ?? $defaultService);
                $prev = $coord;
            }
        });
    }

    /**
     * The route's start moment: route.start_time → tenant day_start → config, on
     * the company's own clock for the route's date.
     */
    private function startTime(Route $route): Carbon
    {
        $settings = is_array($route->tenant?->settings) ? $route->tenant->settings : [];
        $routeStart = $route->getAttribute('start_time');
        $time = is_string($routeStart) && $routeStart !== ''
            ? $routeStart
            : (is_string($settings['day_start'] ?? null) ? $settings['day_start'] : (string) config('routing.day_start'));

        [$h, $m] = array_pad(explode(':', $time), 2, '0');
        $tz = $route->tenant?->timezoneName() ?? (string) config('app.timezone');

        return Carbon::parse($route->scheduled_date->toDateString(), $tz)->setTime((int) $h, (int) $m)->utc();
    }

    /**
     * The depot the agent leaves from (the company's address), or null when unset;
     * then the first stop's arrival is simply the start time (no opening drive leg).
     *
     * @return array{0: float, 1: float}|null
     */
    private function startPoint(Route $route): ?array
    {
        return $route->tenant?->coordinates();
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    private function driveMinutes(array $a, array $b, float $roadFactor, float $speed, int $min): int
    {
        $km = Geo::km($a, $b) * $roadFactor;

        return max($min, (int) ceil($km / $speed * 60));
    }
}
