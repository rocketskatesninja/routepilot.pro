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
 * Each account type lands on its own dashboard surface.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

test('super admin lands on the platform dashboard', function () {
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dashboards/Platform')->has('stats.tenants'));
});

test('a tenant admin lands on the admin dashboard', function () {
    $admin = User::factory()->for($this->tenant)->create();

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dashboards/Admin')->has('stats.today_stops'));
});

test('an agent lands on the agent dashboard with today\'s stops', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $agent->id, 'scheduled_date' => today()]);
    RouteStop::factory()->for($route)->for($pool)->create(['stop_order' => 1, 'status' => 'pending']);

    $this->actingAs($agent)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboards/Agent')
            ->where('stats.today_total', 1)
            ->has('today_stops', 1)
        );
});

test('a customer lands on the customer dashboard with their pools', function () {
    $portalUser = User::factory()->customer()->for($this->tenant)->create();
    $customer = Customer::factory()->for($this->tenant)->create();
    $customer->forceFill(['user_id' => $portalUser->id])->save();
    Pool::factory()->for($this->tenant)->for($customer)->create(['name' => 'Backyard Pool']);

    $this->actingAs($portalUser)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboards/Customer')
            ->has('pools', 1)
            ->where('pools.0.name', 'Backyard Pool')
        );
});
