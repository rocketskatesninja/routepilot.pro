<?php

declare(strict_types=1);

use App\Events\AgentLocationUpdated;
use App\Models\AgentLocation;
use App\Models\Route;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
});

function routeToday(Tenant $tenant, User $agent): void
{
    Route::factory()->for($tenant)->create(['agent_id' => $agent->id, 'scheduled_date' => today(), 'status' => 'in_progress']);
}

test('a ping from an agent on an active route stores + broadcasts the location', function () {
    Event::fake([AgentLocationUpdated::class]);
    routeToday($this->tenant, $this->agent);

    $this->actingAs($this->agent)
        ->postJson('/api/field/ping', ['lat' => 31.2, 'lng' => -81.5, 'heading' => 90, 'accuracy' => 12])
        ->assertOk()->assertJsonPath('tracking', true);

    $loc = AgentLocation::query()->where('agent_id', $this->agent->id)->firstOrFail();
    expect($loc->lat)->toBe(31.2)->and($loc->lng)->toBe(-81.5);

    Event::assertDispatched(AgentLocationUpdated::class, fn (AgentLocationUpdated $e): bool => $e->agentId === $this->agent->id
        && $e->tenantId === $this->tenant->id
        && $e->lat === 31.2);
});

test('a ping without a route today is refused (tracking:false, nothing stored)', function () {
    Event::fake([AgentLocationUpdated::class]);

    $this->actingAs($this->agent)
        ->postJson('/api/field/ping', ['lat' => 31.2, 'lng' => -81.5])
        ->assertOk()->assertJsonPath('tracking', false);

    expect(AgentLocation::query()->count())->toBe(0);
    Event::assertNotDispatched(AgentLocationUpdated::class);
});

test('repeated pings upsert a single row', function () {
    routeToday($this->tenant, $this->agent);

    $this->actingAs($this->agent)->postJson('/api/field/ping', ['lat' => 31.2, 'lng' => -81.5])->assertOk();
    $this->actingAs($this->agent)->postJson('/api/field/ping', ['lat' => 31.25, 'lng' => -81.55])->assertOk();

    expect(AgentLocation::query()->where('agent_id', $this->agent->id)->count())->toBe(1)
        ->and(AgentLocation::query()->where('agent_id', $this->agent->id)->firstOrFail()->lat)->toBe(31.25);
});

test('a customer cannot ping', function () {
    $customer = User::factory()->customer()->for($this->tenant)->create();
    $this->actingAs($customer)->postJson('/api/field/ping', ['lat' => 31.2, 'lng' => -81.5])->assertForbidden();
});

test('out-of-range coordinates are rejected', function () {
    routeToday($this->tenant, $this->agent);
    $this->actingAs($this->agent)->postJson('/api/field/ping', ['lat' => 200, 'lng' => -81.5])->assertInvalid('lat');
});
