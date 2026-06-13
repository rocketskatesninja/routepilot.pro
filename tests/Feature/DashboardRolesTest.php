<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Every role lands on the same customizable grid; the role decides which
 * widgets + defaults it gets.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

test('super admin lands on the grid with platform widgets', function () {
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboards/Grid')
            ->has('layouts.desktop')
            ->has('widgets.platform_stats.tiles')
            ->has('widgets.recent_tenants')
        );
});

test('a tenant admin lands on the grid with admin widgets', function () {
    $admin = User::factory()->for($this->tenant)->create();

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboards/Grid')
            ->has('layouts.desktop')
            ->has('layouts.mobile')
            ->has('palette')
            ->has('catalog')
            ->has('widgets.stats.tiles')
        );
});

test('an agent lands on the grid with their route + day stats', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $agent->id, 'scheduled_date' => today()]);
    RouteStop::factory()->for($route)->for($pool)->create(['stop_order' => 1, 'status' => 'pending']);

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboards/Grid')
            ->where('widgets.agent_stats.tiles.0.value', 1) // Stops today
            ->has('widgets.agent_route.stops', 1)
        );
});

test('a customer lands on the grid with their pools', function () {
    $portalUser = User::factory()->customer()->for($this->tenant)->create();
    $customer = Customer::factory()->for($this->tenant)->create();
    $customer->forceFill(['user_id' => $portalUser->id])->save();
    Pool::factory()->for($this->tenant)->for($customer)->create(['name' => 'Backyard Pool']);

    $this->actingAs($portalUser)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboards/Grid')
            ->has('widgets.my_pools.pools', 1)
            ->where('widgets.my_pools.pools.0.name', 'Backyard Pool')
        );
});
