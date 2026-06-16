<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Compute each stop's `estimated_arrival` for a route. Walk the stops in order
 * from the route's start time, accumulating a drive-time estimate (haversine ×
 * road factor ÷ average speed, floored) between consecutive located stops plus
 * each stop's on-site service duration.
 *
 * A local, zero-cost model — driveMinutes() is the one seam a real drive-time
 * API would replace later. Stops without coordinates get a null ETA and don't
 * advance the clock (they can't be placed in time).
 */
class EstimateArrivals
{
    public function handle(Route $route): void
    {
        $stops = $route->stops()
            ->with(['pool.serviceLocation', 'serviceSubscription.serviceType'])
            ->orderBy('stop_order')
            ->get();

        if ($stops->isEmpty()) {
            return;
        }

        $start = $this->startTime($route);
        $prev = $this->startPoint($route);
        $offset = 0;

        $roadFactor = (float) config('routing.road_factor');
        $speed = max(1.0, (float) config('routing.avg_speed_kmh'));
        $minDrive = (int) config('routing.min_drive_minutes');
        $defaultService = (int) config('routing.default_service_minutes');

        DB::transaction(function () use ($stops, $start, &$prev, &$offset, $roadFactor, $speed, $minDrive, $defaultService): void {
            foreach ($stops as $stop) {
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

    /** The route's start moment: route.start_time → tenant day_start → config. */
    private function startTime(Route $route): Carbon
    {
        $date = $route->scheduled_date->copy();

        $settings = is_array($route->tenant?->settings) ? $route->tenant->settings : [];
        $routeStart = $route->getAttribute('start_time');
        $time = is_string($routeStart) && $routeStart !== ''
            ? $routeStart
            : (is_string($settings['day_start'] ?? null) ? $settings['day_start'] : (string) config('routing.day_start'));

        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return $date->setTime((int) $h, (int) $m);
    }

    /**
     * The depot the agent leaves from (tenant HQ), or null when unset — then
     * the first stop's arrival is simply the start time (no opening drive leg).
     *
     * @return array{0: float, 1: float}|null
     */
    private function startPoint(Route $route): ?array
    {
        $settings = is_array($route->tenant?->settings) ? $route->tenant->settings : [];
        $lat = (float) ($settings['hq_lat'] ?? 0);
        $lng = (float) ($settings['hq_lng'] ?? 0);

        return ($lat === 0.0 && $lng === 0.0) ? null : [$lat, $lng];
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    private function driveMinutes(array $a, array $b, float $roadFactor, float $speed, int $min): int
    {
        $km = $this->haversine($a, $b) * $roadFactor;

        return max($min, (int) ceil($km / $speed * 60));
    }

    /**
     * Haversine great-circle distance in km.
     *
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    private function haversine(array $a, array $b): float
    {
        $earthKm = 6371.0;
        $lat1 = deg2rad($a[0]);
        $lat2 = deg2rad($b[0]);
        $dLat = deg2rad($b[0] - $a[0]);
        $dLng = deg2rad($b[1] - $a[1]);

        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return $earthKm * 2 * atan2(sqrt($h), sqrt(1 - $h));
    }
}
