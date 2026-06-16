<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $this->route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => today()]);
    $this->stop = RouteStop::factory()->for($this->route)->for($this->pool)->create(['stop_order' => 1, 'status' => 'pending']);
});

test('today returns the agent\'s route bundle with stops and inventory', function () {
    $this->actingAs($this->agent)
        ->getJson('/api/field/today')
        ->assertOk()
        ->assertJsonPath('date', today()->toDateString())
        ->assertJsonPath('agent.id', $this->agent->id)
        ->assertJsonCount(1, 'stops')
        ->assertJsonPath('stops.0.id', $this->stop->id)
        ->assertJsonPath('stops.0.pool.name', $this->pool->name)
        ->assertJsonStructure(['stops' => [['id', 'order', 'status', 'pool' => ['name', 'volume_gallons'], 'service', 'last_reading']], 'inventory']);
});

test('today only returns the acting agent\'s own stops', function () {
    // A second agent's route for the same day must not leak into the first agent's bundle.
    $other = User::factory()->agent()->for($this->tenant)->create();
    $otherRoute = Route::factory()->for($this->tenant)->create(['agent_id' => $other->id, 'scheduled_date' => today()]);
    RouteStop::factory()->for($otherRoute)->for($this->pool)->create(['stop_order' => 1]);

    $this->actingAs($this->agent)->getJson('/api/field/today')->assertOk()->assertJsonCount(1, 'stops');
});

test('completing a visit is idempotent under a replayed key', function () {
    $payload = [
        'idempotency_key' => 'field-test-key-123',
        'free_chlorine' => 3.0,
        'ph' => 7.4,
        'alkalinity' => 90,
        'tasks' => [],
        'treatments' => [],
        'notes' => 'Looks good.',
    ];

    $first = $this->actingAs($this->agent)->postJson("/api/field/visits/{$this->stop->id}/complete", $payload);
    $first->assertOk()->assertJsonPath('ok', true)->assertJsonPath('idempotent', false);
    $visitId = $first->json('visit_id');

    // Replaying the SAME key must not create a second visit.
    $this->actingAs($this->agent)->postJson("/api/field/visits/{$this->stop->id}/complete", $payload)
        ->assertOk()
        ->assertJsonPath('idempotent', true)
        ->assertJsonPath('visit_id', $visitId);

    expect(ServiceVisit::query()->where('route_stop_id', $this->stop->id)->count())->toBe(1);
});

test('completing a visit stores the agent GPS proof-of-presence', function () {
    $this->actingAs($this->agent)
        ->postJson("/api/field/visits/{$this->stop->id}/complete", [
            'ph' => 7.4,
            'completed_lat' => 31.1503,
            'completed_lng' => -81.4915,
        ])
        ->assertOk();

    $visit = ServiceVisit::query()->where('route_stop_id', $this->stop->id)->firstOrFail();
    expect((float) $visit->completed_lat)->toEqualWithDelta(31.1503, 0.0001)
        ->and((float) $visit->completed_lng)->toEqualWithDelta(-81.4915, 0.0001);
});

test('an agent cannot complete a stop on another tenant\'s route', function () {
    $otherTenant = Tenant::factory()->create();
    $otherPool = Pool::factory()->for($otherTenant)->create();
    $otherRoute = Route::factory()->for($otherTenant)->create(['scheduled_date' => today()]);
    $foreignStop = RouteStop::factory()->for($otherRoute)->for($otherPool)->create(['stop_order' => 1]);

    // Route is tenant-scoped, so from the agent's context the foreign stop's route is null → 404.
    $this->actingAs($this->agent)
        ->postJson("/api/field/visits/{$foreignStop->id}/complete", ['ph' => 7.4])
        ->assertNotFound();
});

test('a customer cannot reach the field API', function () {
    $this->actingAs(User::factory()->customer()->for($this->tenant)->create())
        ->postJson("/api/field/visits/{$this->stop->id}/complete", ['ph' => 7.4])
        ->assertForbidden();
});
