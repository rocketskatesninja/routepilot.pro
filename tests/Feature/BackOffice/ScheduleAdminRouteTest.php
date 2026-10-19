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
    $this->admin = User::factory()->for($this->tenant)->create(['first_name' => 'Glynn', 'last_name' => 'Owner']);
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $customer = Customer::factory()->for($this->tenant)->create();
    $this->agentRoute = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => today()]);
    $this->stop = RouteStop::factory()->for($this->agentRoute)->for(Pool::factory()->for($this->tenant)->for($customer))->create(['status' => 'pending', 'stop_order' => 1]);
});

test('an admin gets their own card first, even on a day they have no stops', function () {
    $this->actingAs($this->admin)
        ->get('/schedule')
        ->assertInertia(fn (Assert $page) => $page
            ->has('routes', 2)
            ->where('routes.0.id', null)
            ->where('routes.0.agent_id', $this->admin->id)
            ->where('routes.0.agent', 'Glynn Owner')
            ->where('routes.0.total', 0)
            ->where('routes.1.agent_id', $this->agent->id)
        );

    expect(Route::query()->where('agent_id', $this->admin->id)->exists())->toBeFalse();
});

test('an admin who already has a route that day gets no second card', function () {
    $mine = Route::factory()->for($this->tenant)->create(['agent_id' => $this->admin->id, 'scheduled_date' => today()]);

    $this->actingAs($this->admin)
        ->get('/schedule')
        ->assertInertia(fn (Assert $page) => $page->has('routes', 2)->where('routes.0.id', $mine->id));
});

test('agents get no empty card of their own', function () {
    $other = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($other)
        ->get('/schedule')
        ->assertInertia(fn (Assert $page) => $page->has('routes', 1)->where('routes.0.agent_id', $this->agent->id));
});

test('dropping a stop on the admin\'s unsaved card puts it on their route for the day', function () {
    $this->actingAs($this->admin)->post('/schedule/arrange', [
        'date' => today()->toDateString(),
        'routes' => [
            ['id' => null, 'agent_id' => $this->admin->id, 'stop_ids' => [$this->stop->id]],
            ['id' => $this->agentRoute->id, 'agent_id' => $this->agent->id, 'stop_ids' => []],
        ],
    ])->assertRedirect();

    $mine = Route::query()->where('agent_id', $this->admin->id)->whereDate('scheduled_date', today())->sole();
    expect($this->stop->fresh()?->route_id)->toBe($mine->id);
});

test('an unsaved card can\'t create a route for someone else', function () {
    $other = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($this->admin)->post('/schedule/arrange', [
        'date' => today()->toDateString(),
        'routes' => [['id' => null, 'agent_id' => $other->id, 'stop_ids' => [$this->stop->id]]],
    ])->assertRedirect();

    expect(Route::query()->where('agent_id', $other->id)->exists())->toBeFalse()
        ->and($this->stop->fresh()?->route_id)->toBe($this->agentRoute->id);
});

test('an admin can pick their own route colour', function () {
    $this->actingAs($this->admin)
        ->patch("/agents/{$this->admin->id}/color", ['map_color' => '#AA3300'])
        ->assertRedirect();

    expect($this->admin->fresh()?->map_color)->toBe('#aa3300');
});
