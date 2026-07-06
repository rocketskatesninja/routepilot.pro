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

test('an admin can unskip a skipped stop', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => today()]);
    $stop = RouteStop::factory()->for($route)->for($pool)->create(['status' => 'skipped', 'skip_reason' => 'Skipped by office', 'stop_order' => 1]);

    $this->actingAs($this->admin)->post("/stops/{$stop->id}/unskip")->assertRedirect();

    expect($stop->fresh()?->status)->toBe('pending')
        ->and($stop->fresh()?->skip_reason)->toBeNull();
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

// --- Agent+ self-manages their own route only ---

/** An agent with the agent_plus flag forced on (not mass-assignable). */
function plusAgent(Tenant $tenant): User
{
    $agent = User::factory()->agent()->for($tenant)->create();
    $agent->forceFill(['agent_plus' => true])->save();

    return $agent;
}

function stopOn(Route $route, Tenant $tenant, int $order): RouteStop
{
    $pool = Pool::factory()->for($tenant)->for(Customer::factory()->for($tenant)->create())->create();

    return RouteStop::factory()->for($route)->for($pool)->create(['status' => 'pending', 'stop_order' => $order]);
}

test('an Agent+ can skip a stop on their own route', function () {
    $plus = plusAgent($this->tenant);
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $plus->id, 'scheduled_date' => today()]);
    $stop = stopOn($route, $this->tenant, 1);

    $this->actingAs($plus)->post("/stops/{$stop->id}/skip")->assertRedirect();

    expect($stop->fresh()?->status)->toBe('skipped')
        ->and($stop->fresh()?->skip_reason)->toBe('Skipped by tech');
});

test('an Agent+ cannot skip a stop on another route', function () {
    $plus = plusAgent($this->tenant);
    $other = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => today()]);
    $stop = stopOn($other, $this->tenant, 1);

    $this->actingAs($plus)->post("/stops/{$stop->id}/skip")->assertForbidden();
    expect($stop->fresh()?->status)->toBe('pending');
});

test('a plain agent cannot skip a stop on their own route', function () {
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => today()]);
    $stop = stopOn($route, $this->tenant, 1);

    $this->actingAs($this->agent)->post("/stops/{$stop->id}/skip")->assertForbidden();
});

test('an Agent+ can reorder their own route but not another', function () {
    $plus = plusAgent($this->tenant);
    $mine = Route::factory()->for($this->tenant)->create(['agent_id' => $plus->id, 'scheduled_date' => today()]);
    $m1 = stopOn($mine, $this->tenant, 1);
    $m2 = stopOn($mine, $this->tenant, 2);

    $theirs = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => today()]);
    $t1 = stopOn($theirs, $this->tenant, 1);
    $t2 = stopOn($theirs, $this->tenant, 2);

    $this->actingAs($plus)->post('/schedule/arrange', ['routes' => [
        ['id' => $mine->id, 'stop_ids' => [$m2->id, $m1->id]],
        ['id' => $theirs->id, 'stop_ids' => [$t2->id, $t1->id]],
    ]])->assertRedirect();

    // Own route reordered…
    expect($m1->fresh()?->stop_order)->toBe(2)->and($m2->fresh()?->stop_order)->toBe(1);
    // …the other agent's route is untouched.
    expect($t1->fresh()?->stop_order)->toBe(1)->and($t2->fresh()?->stop_order)->toBe(2);
});

test('an Agent+ can optimize their own route but not another', function () {
    $plus = plusAgent($this->tenant);
    $mine = Route::factory()->for($this->tenant)->create(['agent_id' => $plus->id, 'scheduled_date' => today()]);
    $theirs = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => today()]);

    $this->actingAs($plus)->post("/routes/{$mine->id}/optimize")->assertRedirect();
    $this->actingAs($plus)->post("/routes/{$theirs->id}/optimize")->assertForbidden();
});

test('the schedule view tells an Agent+ which route they manage', function () {
    $plus = plusAgent($this->tenant);

    $this->actingAs($plus)->get('/schedule')
        ->assertInertia(fn ($page) => $page->where('manageAgentId', $plus->id)->where('canManage', false));

    // A plain agent manages nothing.
    $this->actingAs($this->agent)->get('/schedule')
        ->assertInertia(fn ($page) => $page->where('manageAgentId', null)->where('canManage', false));
});
