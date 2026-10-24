<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $this->route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => $this->tenant->today()]);
    $this->stop = RouteStop::factory()->for($this->route)->for($this->pool)->create(['stop_order' => 1, 'status' => 'pending']);
});

test('today returns the agent\'s route bundle with stops and inventory', function () {
    $this->actingAs($this->agent)
        ->getJson('/api/field/today')
        ->assertOk()
        ->assertJsonPath('date', $this->tenant->today()->toDateString())
        ->assertJsonPath('agent.id', $this->agent->id)
        ->assertJsonCount(1, 'stops')
        ->assertJsonPath('stops.0.id', $this->stop->id)
        ->assertJsonPath('stops.0.pool.name', $this->pool->name)
        ->assertJsonStructure(['stops' => [['id', 'order', 'status', 'pool' => ['name', 'volume_gallons'], 'service', 'last_reading']], 'inventory']);
});

test('today gives the tech no arrival estimates', function () {
    $this->actingAs($this->agent)->getJson('/api/field/today')->assertOk()
        ->assertJsonPath('stops.0.id', $this->stop->id)
        ->assertJsonMissingPath('stops.0.eta');
});

test('today only returns the acting agent\'s own stops', function () {
    // A second agent's route for the same day must not leak into the first agent's bundle.
    $other = User::factory()->agent()->for($this->tenant)->create();
    $otherRoute = Route::factory()->for($this->tenant)->create(['agent_id' => $other->id, 'scheduled_date' => $this->tenant->today()]);
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

test('each stop sends its own service type\'s visit sections to the field app', function () {
    app()->instance('tenant_id', $this->tenant->id);
    $clean = ServiceType::factory()->for($this->tenant)->create(['name' => 'Weekly Clean', 'field_modules' => ['chemistry' => false, 'treatments' => false]]);
    $chem = ServiceType::factory()->for($this->tenant)->create(['name' => 'Chemistry Check', 'field_modules' => null]);
    $cleanPlan = $this->pool->subscriptions()->create(['service_type_id' => $clean->id, 'assigned_agent_id' => $this->agent->id, 'frequency' => 'weekly', 'status' => 'active']);
    $chemPlan = $this->pool->subscriptions()->create(['service_type_id' => $chem->id, 'assigned_agent_id' => $this->agent->id, 'frequency' => 'weekly', 'status' => 'active']);
    $this->stop->update(['service_subscription_id' => $cleanPlan->id]);
    RouteStop::factory()->for($this->route)->for($this->pool)->create(['stop_order' => 2, 'status' => 'pending', 'service_subscription_id' => $chemPlan->id]);

    $this->actingAs($this->agent)->getJson('/api/field/today')->assertOk()
        // Same pool, two plans: each stop is its own service, not the pool's first plan.
        ->assertJsonPath('stops.0.service.name', 'Weekly Clean')
        ->assertJsonPath('stops.0.service.modules', ['tasks' => true, 'chemistry' => false, 'treatments' => false, 'photos' => true])
        ->assertJsonPath('stops.1.service.name', 'Chemistry Check')
        // Never-configured service types keep every section.
        ->assertJsonPath('stops.1.service.modules', ['tasks' => true, 'chemistry' => true, 'treatments' => true, 'photos' => true]);
});

/** The checklist the field app shows for the test stop, with the pool on a plan with these tasks. */
function checklistFor(string $sanitizer, array $serviceTasks): array
{
    $type = ServiceType::factory()->for(test()->tenant)->create(['tasks' => $serviceTasks]);
    $plan = ServiceSubscription::factory()->for(test()->tenant)->for(test()->pool)->for($type)->create(['status' => 'active', 'assigned_agent_id' => test()->agent->id]);
    test()->stop->update(['service_subscription_id' => $plan->id]);
    test()->pool->update(['sanitizer_type' => $sanitizer]);

    return test()->actingAs(test()->agent)->getJson('/api/field/today')->assertOk()->json('stops.0.service.tasks');
}

test('every visit checklist ends with the routine check for the pool\'s sanitizer', function () {
    expect(checklistFor('chlorine', ['Skim surface', 'Brush walls']))->toBe(['Skim surface', 'Brush walls', 'Check and fill the chlorinator'])
        ->and(checklistFor('salt', ['Skim surface']))->toBe(['Skim surface', 'Check the salt cell'])
        ->and(checklistFor('bromine', ['Skim surface']))->toBe(['Skim surface']);
});

test('a sanitizer check the service already lists isn\'t added twice', function () {
    expect(checklistFor('salt', ['Skim surface', 'check the SALT cell']))->toBe(['Skim surface', 'check the SALT cell']);
});

test('a refused visit says why in words a tech can read, and the server logs it', function () {
    Log::spy();

    $this->actingAs($this->agent)
        ->postJson("/api/field/visits/{$this->stop->id}/complete", ['ph' => 20, 'cyanuric_acid' => 3200])
        ->assertUnprocessable()
        ->assertJsonPath('errors.ph.0', 'The pH field must not be greater than 14.')
        ->assertJsonPath('errors.cyanuric_acid.0', 'The CYA field must not be greater than 1000.');

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context) => $message === 'Field visit refused' && $context['stop_id'] === $this->stop->id && isset($context['errors']['ph']))
        ->once();
});
