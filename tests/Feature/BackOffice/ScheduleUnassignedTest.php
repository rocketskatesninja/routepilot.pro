<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
});

test('the day view surfaces the unassigned bucket with its stops', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create(['name' => 'Lonely Pool']);
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => null, 'scheduled_date' => today()]);
    RouteStop::factory()->for($route)->for($pool)->create(['stop_order' => 1, 'status' => 'pending']);

    $this->actingAs($this->admin)
        ->get('/schedule')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('schedule/Index')
            ->where('unassigned.agent', null)
            ->where('unassigned.total', 1)
            ->where('unassigned.stops.0.pool', 'Lonely Pool')
            ->has('routes', 0)
        );
});

test('visiting the day creates an unassigned route as a drop target', function () {
    expect(Route::query()->whereNull('agent_id')->count())->toBe(0);

    $this->actingAs($this->admin)
        ->get('/schedule')
        ->assertInertia(fn (Assert $page) => $page->where('unassigned.total', 0));

    expect(Route::query()->whereNull('agent_id')->whereDate('scheduled_date', today())->count())->toBe(1);
});

test('arranging an unassigned stop onto an agent route assigns it', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $unassigned = Route::factory()->for($this->tenant)->create(['agent_id' => null, 'scheduled_date' => today()]);
    $agentRoute = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => today()]);
    $stop = RouteStop::factory()->for($unassigned)->for($pool)->create(['status' => 'pending', 'stop_order' => 1]);

    $this->actingAs($this->admin)->post('/schedule/arrange', [
        'routes' => [
            ['id' => $unassigned->id, 'stop_ids' => []],
            ['id' => $agentRoute->id, 'stop_ids' => [$stop->id]],
        ],
    ])->assertRedirect();

    expect($stop->fresh()?->route_id)->toBe($agentRoute->id);
});

test('arranging an assigned stop onto the unassigned route un-assigns it', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $unassigned = Route::factory()->for($this->tenant)->create(['agent_id' => null, 'scheduled_date' => today()]);
    $agentRoute = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => today()]);
    $stop = RouteStop::factory()->for($agentRoute)->for($pool)->create(['status' => 'pending', 'stop_order' => 1]);

    $this->actingAs($this->admin)->post('/schedule/arrange', [
        'routes' => [
            ['id' => $agentRoute->id, 'stop_ids' => []],
            ['id' => $unassigned->id, 'stop_ids' => [$stop->id]],
        ],
    ])->assertRedirect();

    expect($stop->fresh()?->route_id)->toBe($unassigned->id);
});

test('a foreign-tenant unassigned route cannot receive this tenant\'s stop', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $agentRoute = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => today()]);
    $stop = RouteStop::factory()->for($agentRoute)->for($pool)->create(['status' => 'pending', 'stop_order' => 1]);

    $other = Tenant::factory()->create();
    $foreignUnassigned = Route::factory()->for($other)->create(['agent_id' => null, 'scheduled_date' => today()]);

    $this->actingAs($this->admin)->post('/schedule/arrange', [
        'routes' => [
            ['id' => $foreignUnassigned->id, 'stop_ids' => [$stop->id]],
        ],
    ])->assertRedirect();

    // The foreign route is skipped (tenant-scoped) — the stop stays put.
    expect($stop->fresh()?->route_id)->toBe($agentRoute->id);
});
