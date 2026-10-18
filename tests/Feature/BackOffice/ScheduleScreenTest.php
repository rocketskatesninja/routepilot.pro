<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
});

test('the day view groups stops under their route/agent', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create(['name' => 'Smith Pool']);
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => today()]);
    RouteStop::factory()->for($route)->for($pool)->create([
        // ETAs are real moments: 8:05 on the company's clock.
        'stop_order' => 1, 'status' => 'pending', 'estimated_arrival' => Carbon::parse(today()->toDateString().' 08:05', $this->tenant->timezone)->utc(),
    ]);

    $this->actingAs($this->admin)
        ->get('/schedule')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('schedule/Index')
            ->where('date', today()->toDateString())
            ->has('routes', 1)
            ->where('routes.0.total', 1)
            ->where('routes.0.stops.0.pool', 'Smith Pool')
            ->where('routes.0.stops.0.eta', '8:05 AM')
        );
});

test('a different date shows no routes', function () {
    Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => today()]);

    $this->actingAs($this->admin)
        ->get('/schedule?date='.today()->addDays(3)->toDateString())
        ->assertInertia(fn (Assert $page) => $page->has('routes', 0));
});

test('customers are denied the Schedule screen', function () {
    $portalUser = User::factory()->customer()->for($this->tenant)->create();

    $this->actingAs($portalUser)->get('/schedule')->assertForbidden();
});
