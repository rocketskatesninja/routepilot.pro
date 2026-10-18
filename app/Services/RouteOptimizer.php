<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Route;
use App\Models\RouteStop;
use App\Support\Geo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Route optimizer — nearest-neighbor + 2-opt improvement over a route's
 * pending stops, ranked by Haversine great-circle distance.
 *
 * Zero external dependencies; close-enough proxy for drive time over
 * short urban distances. Swap for a drive-time API later without
 * touching callers. Every stop that isn't pending (done, skipped, missed,
 * under way) keeps its place at the front; only the pending tail is
 * reordered, starting from the last of those that has a location, else the
 * company's address, else simply the first pending stop.
 *
 * Typical routes (5–15 stops) optimize in single-digit milliseconds.
 */
class RouteOptimizer
{
    /**
     * Optimize a route's pending stops in place (renumbers every stop_order 1..n).
     *
     * @return array{optimized: int, skipped_no_location: int, total_distance_km: float}
     */
    public function optimize(Route $route): array
    {
        $allStops = $route->stops()
            ->with('pool.serviceLocation')
            ->orderBy('stop_order')
            ->get();

        $fixed = $allStops->where('status', '!=', 'pending')->values();
        $pending = $allStops->where('status', 'pending')->values();

        // Partition pending stops into located (optimizable) and
        // unlocated (appended at the tail in their original order).
        /** @var list<array{stop: RouteStop, coord: array{0: float, 1: float}}> $located */
        $located = [];
        /** @var list<RouteStop> $unlocated */
        $unlocated = [];
        foreach ($pending as $stop) {
            $coord = $stop->pool?->coordinates();
            if ($coord === null) {
                $unlocated[] = $stop;
            } else {
                $located[] = ['stop' => $stop, 'coord' => $coord];
            }
        }

        // A 0–1 stop tour is already optimal.
        if (count($located) < 2) {
            return [
                'optimized' => 0,
                'skipped_no_location' => count($unlocated),
                'total_distance_km' => 0.0,
            ];
        }

        $coords = array_column($located, 'coord');
        $start = $this->startPoint($fixed, $route) ?? $coords[0];

        $order = $this->nearestNeighbor($start, $coords);
        $order = $this->twoOpt($order, $coords, $start);
        $totalDistance = $this->tourDistance($order, $coords, $start);

        // Transactional write: either every stop gets its new order or nothing
        // changes (also guards against a concurrent drag-reorder). Numbering the
        // fixed stops too keeps every order unique.
        DB::transaction(function () use ($order, $located, $unlocated, $fixed) {
            $next = 1;
            foreach ($fixed as $stop) {
                $stop->update(['stop_order' => $next++]);
            }
            foreach ($order as $idx) {
                $located[$idx]['stop']->update(['stop_order' => $next++]);
            }
            foreach ($unlocated as $stop) {
                $stop->update(['stop_order' => $next++]);
            }
        });

        return [
            'optimized' => count($order),
            'skipped_no_location' => count($unlocated),
            'total_distance_km' => round($totalDistance, 2),
        ];
    }

    /**
     * Where the pending tail starts: the last finished-or-under-way stop that has
     * a location (where the agent is now), else the company's address.
     *
     * @param  Collection<int, RouteStop>  $fixed
     * @return array{0: float, 1: float}|null
     */
    private function startPoint(Collection $fixed, Route $route): ?array
    {
        foreach ($fixed->reverse() as $stop) {
            $coord = $stop->pool?->coordinates();
            if ($coord !== null) {
                return $coord;
            }
        }

        return $route->tenant?->coordinates();
    }

    /**
     * Greedy nearest-neighbor tour; returns visit order as indices into $coords.
     *
     * @param  array{0: float, 1: float}  $start
     * @param  list<array{0: float, 1: float}>  $coords
     * @return list<int>
     */
    private function nearestNeighbor(array $start, array $coords): array
    {
        $remaining = array_keys($coords);
        $order = [];
        $current = $start;

        while ($remaining !== []) {
            $bestIdx = null;
            $bestDist = INF;
            foreach ($remaining as $idx) {
                $d = Geo::km($current, $coords[$idx]);
                if ($d < $bestDist) {
                    $bestDist = $d;
                    $bestIdx = $idx;
                }
            }
            $order[] = $bestIdx;
            $current = $coords[$bestIdx];
            $remaining = array_values(array_diff($remaining, [$bestIdx]));
        }

        return $order;
    }

    /**
     * 2-opt local search: reverse any segment whose reversal shortens
     * the tour; iterate to a fixed point (capped). Reliably improves
     * nearest-neighbor output 5–15% on small tours.
     *
     * @param  list<int>  $order
     * @param  list<array{0: float, 1: float}>  $coords
     * @param  array{0: float, 1: float}  $start
     * @return list<int>
     */
    private function twoOpt(array $order, array $coords, array $start): array
    {
        $n = count($order);
        if ($n < 4) {
            return $order;
        }

        $improved = true;
        $maxIterations = 50;
        while ($improved && $maxIterations-- > 0) {
            $improved = false;
            for ($i = 0; $i < $n - 1; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $candidate = $order;
                    $slice = array_reverse(array_slice($candidate, $i, $j - $i + 1));
                    array_splice($candidate, $i, $j - $i + 1, $slice);

                    if ($this->tourDistance($candidate, $coords, $start) < $this->tourDistance($order, $coords, $start)) {
                        $order = $candidate;
                        $improved = true;
                    }
                }
            }
        }

        return $order;
    }

    /**
     * Total tour length from $start through $order. No return leg —
     * the agent doesn't drive back to HQ at day-end.
     *
     * @param  list<int>  $order
     * @param  list<array{0: float, 1: float}>  $coords
     * @param  array{0: float, 1: float}  $start
     */
    private function tourDistance(array $order, array $coords, array $start): float
    {
        $total = 0.0;
        $prev = $start;
        foreach ($order as $idx) {
            $total += Geo::km($prev, $coords[$idx]);
            $prev = $coords[$idx];
        }

        return $total;
    }
}
