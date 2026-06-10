<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceLocation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RouteOptimizer;

/**
 * Route optimizer: nearest-neighbor + 2-opt ordering, locked completed
 * stops, and the no-coordinates tail.
 *
 * Geometry: all pools share lat 28.0 (Florida) and vary only by
 * longitude, so distance is monotonic in |Δlng| and the optimal tour is
 * deterministic. (Coords are deliberately non-zero — the engine treats a
 * 0.0 lat/lng as an unset geocode, the failure sentinel.)
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'settings' => ['hq_lat' => 28.0, 'hq_lng' => -82.10],
    ]);
    app()->instance('tenant_id', $this->tenant->id);

    $this->agent = User::factory()->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->optimizer = new RouteOptimizer;
});

function poolAt(float $lng): Pool
{
    $t = test();
    $pool = Pool::factory()->for($t->tenant)->for($t->customer)->create();
    ServiceLocation::factory()->for($pool)->create(['lat' => 28.0, 'lng' => $lng]);

    return $pool;
}

test('pending stops are reordered nearest-first from HQ', function () {
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id]);

    // p1..p4 ascending longitude (ascending distance from HQ at -82.10).
    $p1 = poolAt(-82.09);
    $p2 = poolAt(-82.08);
    $p3 = poolAt(-82.07);
    $p4 = poolAt(-82.06);

    // Seed them in a deliberately shuffled stop_order: p3, p1, p4, p2.
    foreach ([[$p3, 1], [$p1, 2], [$p4, 3], [$p2, 4]] as [$pool, $order]) {
        RouteStop::factory()->for($route)->for($pool)->create(['stop_order' => $order]);
    }

    $summary = $this->optimizer->optimize($route);

    $ordered = $route->stops()->get()->sortBy('stop_order')->pluck('pool_id')->values()->all();
    expect($ordered)->toBe([$p1->id, $p2->id, $p3->id, $p4->id]) // nearest-first from HQ
        ->and($summary['optimized'])->toBe(4)
        ->and($summary['skipped_no_location'])->toBe(0)
        ->and($summary['total_distance_km'])->toBeGreaterThan(2.5)
        ->and($summary['total_distance_km'])->toBeLessThan(4.0);
});

test('completed stops keep their slots and seed the start point', function () {
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id]);

    // The agent just finished far to the east; remaining pools are to the west.
    $far = poolAt(-82.01);
    $completedStop = RouteStop::factory()->for($route)->for($far)->create(['stop_order' => 1, 'status' => 'completed']);

    $pA = poolAt(-82.09);
    $pB = poolAt(-82.08);
    $pC = poolAt(-82.07);
    foreach ([$pA, $pB, $pC] as $i => $pool) {
        RouteStop::factory()->for($route)->for($pool)->create(['stop_order' => $i + 2]);
    }

    $this->optimizer->optimize($route);

    // Start at -82.01 → nearest is -82.07 (pC), then -82.08 (pB), then -82.09 (pA).
    expect($completedStop->fresh()->stop_order)->toBe(1)
        ->and($route->stops()->get()->sortBy('stop_order')->pluck('pool_id')->values()->all())
        ->toBe([$far->id, $pC->id, $pB->id, $pA->id]);
});

test('stops without coordinates are appended at the tail', function () {
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id]);

    $located = collect([-82.08, -82.09])->map(fn ($lng) => poolAt($lng));
    $unlocated = Pool::factory()->for($this->tenant)->for($this->customer)->create(); // no ServiceLocation

    RouteStop::factory()->for($route)->for($unlocated)->create(['stop_order' => 1]);
    RouteStop::factory()->for($route)->for($located[0])->create(['stop_order' => 2]);
    RouteStop::factory()->for($route)->for($located[1])->create(['stop_order' => 3]);

    $summary = $this->optimizer->optimize($route);

    // From HQ (-82.10): nearest is -82.09 (located[1]) then -82.08 (located[0]); unlocated tacked on last.
    expect($summary['skipped_no_location'])->toBe(1)
        ->and($route->stops()->get()->sortBy('stop_order')->pluck('pool_id')->values()->all())
        ->toBe([$located[1]->id, $located[0]->id, $unlocated->id]);
});

test('a route with fewer than two locatable pending stops is a no-op', function () {
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id]);
    RouteStop::factory()->for($route)->for(poolAt(-82.09))->create(['stop_order' => 1]);

    $summary = $this->optimizer->optimize($route);

    expect($summary)->toBe(['optimized' => 0, 'skipped_no_location' => 0, 'total_distance_km' => 0.0]);
});
