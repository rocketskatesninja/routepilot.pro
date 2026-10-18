<?php

declare(strict_types=1);

use App\Actions\EstimateArrivals;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceLocation;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Drive-time ETA accumulation. Pools share lat 28.0 and vary by longitude;
 * 0.01° ≈ 1 km here, so every leg floors to the 5-minute minimum, making the
 * arithmetic deterministic. HQ (the company's address) sits at -82.10. Arrivals
 * are stored as real moments; plan-time ones are read back on the company's clock.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['lat' => 28.0, 'lng' => -82.10]);
    app()->instance('tenant_id', $this->tenant->id);
    $this->agent = User::factory()->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->action = new EstimateArrivals;
});

/** A stop's arrival as HH:MM on its company's clock. */
function localEta(RouteStop $stop): ?string
{
    $stop = $stop->fresh();

    return $stop?->estimated_arrival?->setTimezone($stop->route->tenant->timezone)->format('H:i');
}

function locatedPool(float $lng): Pool
{
    $t = test();
    $pool = Pool::factory()->for($t->tenant)->for($t->customer)->create();
    ServiceLocation::factory()->for($pool)->create(['lat' => 28.0, 'lng' => $lng]);

    return $pool;
}

test('arrivals accumulate from the start time with drive + service gaps', function () {
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'start_time' => '08:00']);
    $sA = RouteStop::factory()->for($route)->for(locatedPool(-82.09))->create(['stop_order' => 1]);
    $sB = RouteStop::factory()->for($route)->for(locatedPool(-82.08))->create(['stop_order' => 2]);

    $this->action->handle($route);

    // 08:00 + 5 (min drive HQ→A); B = A + 30 (default service) + 5 (drive A→B).
    expect(localEta($sA))->toBe('08:05')
        ->and(localEta($sB))->toBe('08:40');
});

test('a stop with no coordinates gets a null eta and is skipped in the timeline', function () {
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'start_time' => '08:00']);
    $sA = RouteStop::factory()->for($route)->for(locatedPool(-82.09))->create(['stop_order' => 1]);
    $sX = RouteStop::factory()->for($route)->for(Pool::factory()->for($this->tenant)->for($this->customer)->create())->create(['stop_order' => 2]);
    $sB = RouteStop::factory()->for($route)->for(locatedPool(-82.08))->create(['stop_order' => 3]);

    $this->action->handle($route);

    expect($sX->fresh()->estimated_arrival)->toBeNull()
        ->and($sA->fresh()->estimated_arrival)->not->toBeNull()
        ->and($sB->fresh()->estimated_arrival->greaterThan($sA->fresh()->estimated_arrival))->toBeTrue();
});

test('the route start time anchors the first arrival', function () {
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'start_time' => '13:30']);
    $s = RouteStop::factory()->for($route)->for(locatedPool(-82.09))->create(['stop_order' => 1]);

    $this->action->handle($route);

    expect(localEta($s))->toBe('13:35');
});

test('with no HQ set the first stop starts exactly at the start time', function () {
    $tenant = Tenant::factory()->create(['lat' => null, 'lng' => null]);
    app()->instance('tenant_id', $tenant->id);
    $customer = Customer::factory()->for($tenant)->create();
    $route = Route::factory()->for($tenant)->create(['agent_id' => $this->agent->id, 'start_time' => '09:00']);
    $pool = Pool::factory()->for($tenant)->for($customer)->create();
    ServiceLocation::factory()->for($pool)->create(['lat' => 28.0, 'lng' => -82.05]);
    $s = RouteStop::factory()->for($route)->for($pool)->create(['stop_order' => 1]);

    $this->action->handle($route);

    expect(localEta($s))->toBe('09:00'); // no opening drive leg
});

test('a live re-anchor re-times only the pending tail from the given point', function () {
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'start_time' => '08:00']);
    $done = RouteStop::factory()->for($route)->for(locatedPool(-82.09))->create([
        'stop_order' => 1, 'status' => 'completed', 'estimated_arrival' => today()->setTime(8, 0),
    ]);
    $pending = RouteStop::factory()->for($route)->for(locatedPool(-82.08))->create(['stop_order' => 2, 'status' => 'pending']);

    // The agent is at the HQ point right now (14:00 UTC); re-anchor from there.
    $this->action->handle($route, [28.0, -82.10], Carbon::parse('2026-06-16 14:00', 'UTC'));

    // The completed stop keeps its recorded 08:00; the pending one is re-timed to
    // 14:05 UTC (min 5-min drive from the live point), a real moment like the rest.
    expect($done->fresh()->estimated_arrival->format('H:i'))->toBe('08:00')
        ->and($pending->fresh()->estimated_arrival->format('H:i'))->toBe('14:05');
});

test('a stop uses its service-type duration for the gap to the next stop', function () {
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'start_time' => '08:00']);
    $type = ServiceType::factory()->for($this->tenant)->create(['estimated_duration_minutes' => 60]);
    $pA = locatedPool(-82.09);
    $sub = ServiceSubscription::factory()->for($this->tenant)->for($pA)->for($type)->create();
    RouteStop::factory()->for($route)->for($pA)->create(['stop_order' => 1, 'service_subscription_id' => $sub->id]);
    $sB = RouteStop::factory()->for($route)->for(locatedPool(-82.08))->create(['stop_order' => 2]);

    $this->action->handle($route);

    // A at 08:05; B = 08:05 + 60 (service type) + 5 (drive) = 09:10.
    expect(localEta($sB))->toBe('09:10');
});

test('a live re-anchor and the plan agree on the clock the screens show', function () {
    // Plan-time 8:00 local; then the tech pings at 9:30 local and is 5 minutes out.
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => '2026-06-16', 'start_time' => '08:00']);
    $stop = RouteStop::factory()->for($route)->for(locatedPool(-82.08))->create(['stop_order' => 1, 'status' => 'pending']);
    app()->instance('tenant', $this->tenant);

    $this->action->handle($route);
    expect(Tenant::localTime($stop->fresh()->estimated_arrival))->toBe('8:05 AM');

    $this->action->handle($route, [28.0, -82.10], Carbon::parse('2026-06-16 09:30', 'America/New_York')->utc());
    expect(Tenant::localTime($stop->fresh()->estimated_arrival))->toBe('9:35 AM');
});

test('a live re-anchor leaves a missed or under-way stop alone', function () {
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'start_time' => '08:00']);
    $atIt = RouteStop::factory()->for($route)->for(locatedPool(-82.09))->create(['stop_order' => 1, 'status' => 'in_progress', 'estimated_arrival' => null]);

    $this->action->handle($route, [28.0, -82.10], Carbon::parse('2026-06-16 14:00', 'UTC'));

    expect($atIt->fresh()->estimated_arrival)->toBeNull();
});
