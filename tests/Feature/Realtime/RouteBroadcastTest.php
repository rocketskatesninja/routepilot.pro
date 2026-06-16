<?php

declare(strict_types=1);

use App\Actions\CompleteVisit;
use App\Events\RouteUpdated;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceLocation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
});

test('the tenant schedule channel authorizes only that tenant\'s staff', function () {
    $broadcaster = app(BroadcastFactory::class)->connection();
    $channels = (new ReflectionProperty(Broadcaster::class, 'channels'))->getValue($broadcaster);
    $authorize = $channels['tenant.{tenantId}'];

    $foreignStaff = User::factory()->for(Tenant::factory()->create())->create();
    $customerUser = User::factory()->customer()->for($this->tenant)->create();

    expect($authorize($this->admin, $this->tenant->id))->toBeTrue()
        ->and($authorize($this->agent, $this->tenant->id))->toBeTrue()
        ->and($authorize($foreignStaff, $this->tenant->id))->toBeFalse()
        ->and($authorize($customerUser, $this->tenant->id))->toBeFalse();
});

test('optimizing a route broadcasts RouteUpdated for that day', function () {
    Event::fake([RouteUpdated::class]);
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => today()]);
    foreach ([-82.09, -82.08] as $i => $lng) {
        $pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
        ServiceLocation::factory()->for($pool)->create(['lat' => 28.0, 'lng' => $lng]);
        RouteStop::factory()->for($route)->for($pool)->create(['stop_order' => $i + 1]);
    }

    $this->actingAs($this->admin)->post("/routes/{$route->id}/optimize")->assertRedirect();

    Event::assertDispatched(RouteUpdated::class, fn (RouteUpdated $e): bool => $e->tenantId === $this->tenant->id
        && $e->date === today()->toDateString()
        && $e->agentId === $this->agent->id);
});

test('skipping a stop broadcasts RouteUpdated', function () {
    Event::fake([RouteUpdated::class]);
    $pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => today()]);
    $stop = RouteStop::factory()->for($route)->for($pool)->create(['stop_order' => 1, 'status' => 'pending']);

    $this->actingAs($this->admin)->post("/stops/{$stop->id}/skip")->assertRedirect();

    Event::assertDispatched(RouteUpdated::class, fn (RouteUpdated $e): bool => $e->date === today()->toDateString());
});

test('completing a visit broadcasts RouteUpdated', function () {
    Event::fake([RouteUpdated::class]);
    $pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => today()]);
    $stop = RouteStop::factory()->for($route)->for($pool)->create(['stop_order' => 1, 'status' => 'pending']);

    app(CompleteVisit::class)->handle($stop, ['free_chlorine' => 3.0, 'ph' => 7.4, 'alkalinity' => 90, 'tasks' => [], 'treatments' => []], $this->agent);

    Event::assertDispatched(RouteUpdated::class, fn (RouteUpdated $e): bool => $e->date === today()->toDateString()
        && $e->agentId === $this->agent->id);
});
