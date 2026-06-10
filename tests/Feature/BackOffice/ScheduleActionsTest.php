<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
});

test('materialize generates route stops from active subscriptions', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $type = ServiceType::factory()->for($this->tenant)->create();
    ServiceSubscription::factory()->for($this->tenant)->for($pool)->for($type)->create([
        'assigned_agent_id' => $this->agent->id, 'frequency' => 'weekly', 'preferred_day' => 'tuesday', 'status' => 'active',
    ]);

    $this->actingAs($this->admin)->post('/schedule/materialize')->assertRedirect();

    expect(RouteStop::query()->count())->toBeGreaterThan(0);
});

test('optimize runs on a route without error', function () {
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => today()]);

    $this->actingAs($this->admin)->post("/routes/{$route->id}/optimize")->assertRedirect();
});

test('an admin can skip a pending stop', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => today()]);
    $stop = RouteStop::factory()->for($route)->for($pool)->create(['status' => 'pending', 'stop_order' => 1]);

    $this->actingAs($this->admin)->post("/stops/{$stop->id}/skip")->assertRedirect();

    expect($stop->fresh()?->status)->toBe('skipped');
});

test('a foreign-tenant stop cannot be skipped', function () {
    $other = Tenant::factory()->create();
    $otherCustomer = Customer::factory()->for($other)->create();
    $otherPool = Pool::factory()->for($other)->for($otherCustomer)->create();
    $otherRoute = Route::factory()->for($other)->create(['agent_id' => User::factory()->agent()->for($other)->create()->id, 'scheduled_date' => today()]);
    $foreignStop = RouteStop::factory()->for($otherRoute)->for($otherPool)->create(['status' => 'pending', 'stop_order' => 1]);

    $this->actingAs($this->admin)->post("/stops/{$foreignStop->id}/skip")->assertNotFound();
});

test('agents cannot run schedule actions', function () {
    $this->actingAs($this->agent)->post('/schedule/materialize')->assertForbidden();
});
