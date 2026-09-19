<?php

declare(strict_types=1);

use App\Mail\VisitRecapMail;
use App\Models\ChemicalInventory;
use App\Models\Customer;
use App\Models\InventoryTransaction;
use App\Models\NotificationPreference;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

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

test('the agent completes a visit, writing reading + tasks + treatments', function () {
    $this->actingAs($this->agent)
        ->post("/api/field/visits/{$this->stop->id}/complete", visitPayload([
            'treatments' => [['name' => 'Liquid Chlorine', 'amount' => 32, 'unit' => 'oz']],
        ]))
        ->assertOk();

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
        ->post("/api/field/visits/{$this->stop->id}/complete", visitPayload(['photos' => [UploadedFile::fake()->image('after.jpg')]]))
        ->assertOk();

    $visit = ServiceVisit::query()->where('route_stop_id', $this->stop->id)->first();
    expect($visit?->photos()->count())->toBe(1);
});

test('completing a visit notifies the homeowner portal user', function () {
    $portalUser = User::factory()->customer()->for($this->tenant)->create();
    $this->pool->customer->forceFill(['user_id' => $portalUser->id])->save();

    $this->actingAs($this->agent)->post("/api/field/visits/{$this->stop->id}/complete", visitPayload())->assertOk();

    expect($portalUser->notifications()->count())->toBe(1);
});

test('a homeowner who opted out gets no service notification', function () {
    $portalUser = User::factory()->customer()->for($this->tenant)->create();
    $this->pool->customer->forceFill(['user_id' => $portalUser->id])->save();
    NotificationPreference::create(['user_id' => $portalUser->id, 'category' => 'service', 'email' => false, 'in_app' => false]);

    $this->actingAs($this->agent)->post("/api/field/visits/{$this->stop->id}/complete", visitPayload())->assertOk();

    expect($portalUser->notifications()->count())->toBe(0);
});

test('a treatment deducts matching inventory and logs it', function () {
    $item = ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Cal Hypo', 'unit' => 'lbs', 'current_stock' => 10]);

    $this->actingAs($this->agent)
        ->post("/api/field/visits/{$this->stop->id}/complete", visitPayload([
            'treatments' => [['name' => 'Cal Hypo', 'amount' => 2, 'unit' => 'lbs']],
        ]))
        ->assertOk();

    expect((float) $item->fresh()?->current_stock)->toBe(8.0);
    expect(InventoryTransaction::query()->where('chemical_inventory_id', $item->id)->where('type', 'usage')->exists())->toBeTrue();
});

test('re-submitting a stop updates the same visit instead of creating a duplicate', function () {
    $payload = visitPayload([
        'ph' => 7.0,
        'notes' => 'Original.',
        'tasks' => [['name' => 'Skim surface', 'done' => false], ['name' => 'Brush walls', 'done' => false]],
    ]);

    $this->actingAs($this->agent)->post("/api/field/visits/{$this->stop->id}/complete", $payload)->assertOk();

    $first = ServiceVisit::query()->where('route_stop_id', $this->stop->id)->sole();

    // Re-open and re-save with edited values.
    $this->actingAs($this->agent)->post("/api/field/visits/{$this->stop->id}/complete", visitPayload([
        'ph' => 7.6,
        'notes' => 'Edited on a second visit.',
        'tasks' => [['name' => 'Skim surface', 'done' => true], ['name' => 'Brush walls', 'done' => true]],
    ]))->assertOk();

    // Exactly one visit for this stop — no duplicate.
    expect(ServiceVisit::query()->where('route_stop_id', $this->stop->id)->count())->toBe(1);

    $updated = ServiceVisit::query()->where('route_stop_id', $this->stop->id)->sole();
    expect($updated->id)->toBe($first->id);
    expect($updated->notes)->toBe('Edited on a second visit.');
    expect($updated->chemicalReading?->ph)->toBe(7.6);
    expect($updated->tasks()->where('is_completed', true)->count())->toBe(2);
    expect($this->stop->fresh()?->status)->toBe('completed');
});

test('editing a visit does not double-deduct inventory', function () {
    $item = ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Cal Hypo', 'unit' => 'lbs', 'current_stock' => 10]);

    // First completion deducts 2 lbs → 8 remain, one usage transaction.
    $this->actingAs($this->agent)
        ->post("/api/field/visits/{$this->stop->id}/complete", visitPayload([
            'treatments' => [['name' => 'Cal Hypo', 'amount' => 2, 'unit' => 'lbs']],
        ]))
        ->assertOk();

    expect((float) $item->fresh()?->current_stock)->toBe(8.0);

    // Re-save the SAME treatment: the prior deduction is reversed and re-applied,
    // so stock stays at 8 (not 6) and there's still exactly one usage row.
    $this->actingAs($this->agent)
        ->post("/api/field/visits/{$this->stop->id}/complete", visitPayload([
            'treatments' => [['name' => 'Cal Hypo', 'amount' => 2, 'unit' => 'lbs']],
        ]))
        ->assertOk();

    expect((float) $item->fresh()?->current_stock)->toBe(8.0);

    $visit = ServiceVisit::query()->where('route_stop_id', $this->stop->id)->sole();
    expect(InventoryTransaction::query()->where('chemical_inventory_id', $item->id)->where('type', 'usage')->count())->toBe(1);
    expect($visit->treatments()->count())->toBe(1);
});

test('editing a visit re-deducts correctly when the treatment amount changes', function () {
    $item = ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Cal Hypo', 'unit' => 'lbs', 'current_stock' => 10]);

    $this->actingAs($this->agent)
        ->post("/api/field/visits/{$this->stop->id}/complete", visitPayload([
            'treatments' => [['name' => 'Cal Hypo', 'amount' => 2, 'unit' => 'lbs']],
        ]))
        ->assertOk();
    expect((float) $item->fresh()?->current_stock)->toBe(8.0);

    // Bump the amount to 3 lbs: reverse the old 2 (back to 10) then deduct 3 → 7.
    $this->actingAs($this->agent)
        ->post("/api/field/visits/{$this->stop->id}/complete", visitPayload([
            'treatments' => [['name' => 'Cal Hypo', 'amount' => 3, 'unit' => 'lbs']],
        ]))
        ->assertOk();

    expect((float) $item->fresh()?->current_stock)->toBe(7.0);
});

test('editing a visit appends new photos without dropping the originals', function () {
    Storage::fake('public');

    $this->actingAs($this->agent)
        ->post("/api/field/visits/{$this->stop->id}/complete", visitPayload(['photos' => [UploadedFile::fake()->image('before.jpg')]]))
        ->assertOk();

    $this->actingAs($this->agent)
        ->post("/api/field/visits/{$this->stop->id}/complete", visitPayload(['photos' => [UploadedFile::fake()->image('after.jpg')]]))
        ->assertOk();

    $visit = ServiceVisit::query()->where('route_stop_id', $this->stop->id)->sole();
    expect($visit->photos()->count())->toBe(2);
});

test('an agent cannot complete another agent\'s stop', function () {
    $otherAgent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($otherAgent)->post("/api/field/visits/{$this->stop->id}/complete", visitPayload())->assertForbidden();
});

test('a tenant admin can complete any stop', function () {
    $this->actingAs($this->admin)->post("/api/field/visits/{$this->stop->id}/complete", visitPayload())->assertOk();
});

test('completing a visit emails the homeowner a recap', function () {
    Mail::fake();

    $this->actingAs($this->agent)->post("/api/field/visits/{$this->stop->id}/complete", visitPayload())->assertOk();

    Mail::assertQueued(VisitRecapMail::class);
});

test('an opted-out customer gets no recap email', function () {
    Mail::fake();
    $this->pool->customer->update(['email_opt_out' => true]);

    $this->actingAs($this->agent)->post("/api/field/visits/{$this->stop->id}/complete", visitPayload())->assertOk();

    Mail::assertNotQueued(VisitRecapMail::class);
});
