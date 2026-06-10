<?php

declare(strict_types=1);

use App\Models\ChemicalInventory;
use App\Models\Customer;
use App\Models\InventoryTransaction;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();

    $customer = Customer::factory()->for($this->tenant)->create();
    $this->pool = Pool::factory()->for($this->tenant)->for($customer)->create(['name' => 'Backyard Oasis']);
    $type = ServiceType::factory()->for($this->tenant)->create(['tasks' => ['Skim surface', 'Brush walls', 'Test water']]);
    ServiceSubscription::factory()->for($this->tenant)->for($this->pool)->for($type)->create(['assigned_agent_id' => $this->agent->id, 'status' => 'active']);

    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => today()]);
    $this->stop = RouteStop::factory()->for($route)->for($this->pool)->create(['status' => 'pending', 'stop_order' => 1]);
});

/** @return array<string, mixed> */
function visitPayload(array $overrides = []): array
{
    return array_merge([
        'free_chlorine' => 1.2, 'ph' => 7.2, 'alkalinity' => 90, 'calcium_hardness' => 250,
        'cyanuric_acid' => 40, 'salt' => 0, 'water_temperature' => 82,
        'tasks' => [['name' => 'Skim surface', 'done' => true], ['name' => 'Brush walls', 'done' => false]],
        'treatments' => [],
        'notes' => 'Water clear.',
    ], $overrides);
}

test('the assigned agent sees the visit screen', function () {
    $this->actingAs($this->agent)
        ->get("/visit/{$this->stop->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('agent/Visit')->where('pool.name', 'Backyard Oasis')->has('service.tasks', 3));
});

test('the agent completes a visit, writing reading + tasks + treatments', function () {
    $this->actingAs($this->agent)
        ->post("/visit/{$this->stop->id}/complete", visitPayload([
            'treatments' => [['name' => 'Liquid Chlorine', 'amount' => 32, 'unit' => 'oz']],
        ]))
        ->assertRedirect('/dashboard');

    $visit = ServiceVisit::query()->where('route_stop_id', $this->stop->id)->first();
    expect($visit?->status)->toBe('completed');
    expect($visit?->getAttribute('agent_id'))->toBe($this->agent->id);
    expect($visit?->chemicalReading?->lsi_score)->not->toBeNull();
    expect($visit?->treatments()->count())->toBe(1);
    expect($visit?->tasks()->count())->toBe(2);
    expect($this->stop->fresh()?->status)->toBe('completed');
});

test('completing a visit stores uploaded photos', function () {
    Storage::fake('public');

    $this->actingAs($this->agent)
        ->post("/visit/{$this->stop->id}/complete", visitPayload(['photos' => [UploadedFile::fake()->image('after.jpg')]]))
        ->assertRedirect('/dashboard');

    $visit = ServiceVisit::query()->where('route_stop_id', $this->stop->id)->first();
    expect($visit?->photos()->count())->toBe(1);
});

test('a treatment deducts matching inventory and logs it', function () {
    $item = ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Cal Hypo', 'unit' => 'lbs', 'current_stock' => 10]);

    $this->actingAs($this->agent)
        ->post("/visit/{$this->stop->id}/complete", visitPayload([
            'treatments' => [['name' => 'Cal Hypo', 'amount' => 2, 'unit' => 'lbs']],
        ]))
        ->assertRedirect('/dashboard');

    expect((float) $item->fresh()?->current_stock)->toBe(8.0);
    expect(InventoryTransaction::query()->where('chemical_inventory_id', $item->id)->where('type', 'usage')->exists())->toBeTrue();
});

test('analyze returns dosing recommendations as JSON', function () {
    $this->actingAs($this->agent)
        ->postJson("/visit/{$this->stop->id}/analyze", ['free_chlorine' => 0.2, 'ph' => 8.2, 'alkalinity' => 140])
        ->assertOk()
        ->assertJsonStructure(['lsi', 'parameters', 'recommendations']);
});

test('a foreign-tenant stop is not found', function () {
    $other = Tenant::factory()->create();
    $foreignRoute = Route::factory()->for($other)->create(['agent_id' => $this->agent->id, 'scheduled_date' => today()]);
    $foreignPool = Pool::factory()->for($other)->for(Customer::factory()->for($other))->create();
    $foreignStop = RouteStop::factory()->for($foreignRoute)->for($foreignPool)->create(['status' => 'pending', 'stop_order' => 1]);

    $this->actingAs($this->agent)->get("/visit/{$foreignStop->id}")->assertNotFound();
});

test('an agent cannot work another agent\'s stop', function () {
    $otherAgent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($otherAgent)->get("/visit/{$this->stop->id}")->assertForbidden();
});

test('a tenant admin can open any stop', function () {
    $this->actingAs($this->admin)->get("/visit/{$this->stop->id}")->assertOk();
});
