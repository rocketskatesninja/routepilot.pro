<?php

declare(strict_types=1);

use App\Models\ChemicalReading;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceLocation;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

// The panel under the schedule map shows the selected visit: its pool, customer,
// report and the pool's last water test, all sent with the day's stops.
beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/New_York']);
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $this->route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => today()]);
});

function scheduleTestedOn(Pool $pool, User $agent, string $completedAt, float $ph): void
{
    $visit = ServiceVisit::factory()->create(['tenant_id' => $pool->tenant_id, 'pool_id' => $pool->id, 'agent_id' => $agent->id, 'completed_at' => $completedAt]);
    ChemicalReading::factory()->create(['tenant_id' => $pool->tenant_id, 'service_visit_id' => $visit->id, 'ph' => $ph]);
}

test('a stop carries its pool, customer and the pool’s latest water test', function () {
    scheduleTestedOn($this->pool, $this->agent, '2026-10-01 15:00:00', 7.2);
    // 02:30 UTC on Oct 25 is still Oct 24 in New York: the day shown is the company's.
    scheduleTestedOn($this->pool, $this->agent, '2026-10-25 02:30:00', 7.8);
    RouteStop::factory()->for($this->route)->for($this->pool)->create(['status' => 'pending', 'stop_order' => 1]);

    $this->actingAs($this->admin)
        ->get('/schedule')
        ->assertInertia(fn (Assert $page) => $page
            ->where('routes.1.stops.0.pool_id', $this->pool->id)
            ->where('routes.1.stops.0.customer_id', $this->customer->id)
            ->where('routes.1.stops.0.report_id', null)
            ->where('routes.1.stops.0.skip_reason', null)
            ->where('routes.1.stops.0.reading.tested_on', '2026-10-24')
            ->where('routes.1.stops.0.reading.ph', 7.8)
            ->where('routes.1.stops.0.reading.free_chlorine', 2)
        );
});

test('a stop carries where it is, for its pin and the panel', function () {
    ServiceLocation::factory()->create(['pool_id' => $this->pool->id, 'lat' => 31.15, 'lng' => -81.39]);
    $unplaced = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    RouteStop::factory()->for($this->route)->for($this->pool)->create(['status' => 'pending', 'stop_order' => 1]);
    RouteStop::factory()->for($this->route)->for($unplaced)->create(['status' => 'pending', 'stop_order' => 2]);

    $this->actingAs($this->admin)
        ->get('/schedule')
        ->assertInertia(fn (Assert $page) => $page
            ->where('routes.1.stops.0.lat', 31.15)
            ->where('routes.1.stops.0.lng', -81.39)
            ->where('routes.1.stops.0.address', $this->pool->fresh()->serviceLocation->formattedAddress())
            ->where('routes.1.stops.1.lat', null)
            ->where('routes.1.stops.1.address', null)
        );
});

test('a never-tested pool has no reading', function () {
    RouteStop::factory()->for($this->route)->for($this->pool)->create(['status' => 'pending', 'stop_order' => 1]);

    $this->actingAs($this->admin)
        ->get('/schedule')
        ->assertInertia(fn (Assert $page) => $page->where('routes.1.stops.0.reading', null));
});

test('a finished stop links its report; a skipped one gives its reason', function () {
    $done = RouteStop::factory()->for($this->route)->for($this->pool)->create(['status' => 'completed', 'stop_order' => 1]);
    $visit = ServiceVisit::factory()->create(['tenant_id' => $this->tenant->id, 'pool_id' => $this->pool->id, 'agent_id' => $this->agent->id, 'route_stop_id' => $done->id]);
    $other = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    RouteStop::factory()->for($this->route)->for($other)->create(['status' => 'skipped', 'skip_reason' => 'Gate locked', 'stop_order' => 2]);

    $this->actingAs($this->admin)
        ->get('/schedule')
        ->assertInertia(fn (Assert $page) => $page
            ->where('routes.1.stops.0.report_id', $visit->id)
            ->where('routes.1.stops.1.report_id', null)
            ->where('routes.1.stops.1.skip_reason', 'Gate locked')
        );
});
