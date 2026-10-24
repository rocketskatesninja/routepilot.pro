<?php

declare(strict_types=1);

use App\Models\ChemicalInventory;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('an admin can add a chemical', function () {
    $this->actingAs($this->admin)
        ->post('/inventory', ['chemical_name' => 'Muriatic Acid', 'unit' => 'gal', 'current_stock' => 20, 'reorder_threshold' => 5, 'cost_per_unit' => 3])
        ->assertRedirect();

    $item = ChemicalInventory::query()->where('chemical_name', 'Muriatic Acid')->first();
    expect((float) $item?->current_stock)->toBe(20.0);
});

test('an admin can edit a chemical', function () {
    $item = ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Old']);

    $this->actingAs($this->admin)
        ->patch("/inventory/{$item->id}", ['chemical_name' => 'New', 'unit' => $item->unit]) // it has stock, so its unit stays
        ->assertRedirect();

    expect($item->fresh()?->chemical_name)->toBe('New');
});

test('restock and usage move the stock and log a transaction', function () {
    $item = ChemicalInventory::factory()->for($this->tenant)->create(['current_stock' => 10]);

    $this->actingAs($this->admin)->post("/inventory/{$item->id}/adjust", ['type' => 'restock', 'quantity' => 5])->assertRedirect();
    expect((float) $item->fresh()?->current_stock)->toBe(15.0);

    $this->actingAs($this->admin)->post("/inventory/{$item->id}/adjust", ['type' => 'usage', 'quantity' => 3])->assertRedirect();
    expect((float) $item->fresh()?->current_stock)->toBe(12.0);

    expect($item->transactions()->count())->toBe(2);
});

test('an adjustment sets the stock to an exact count', function () {
    $item = ChemicalInventory::factory()->for($this->tenant)->create(['current_stock' => 10]);

    $this->actingAs($this->admin)->post("/inventory/{$item->id}/adjust", ['type' => 'adjustment', 'quantity' => 42])->assertRedirect();

    expect((float) $item->fresh()?->current_stock)->toBe(42.0);
});

test('every change is logged as what actually moved, so the history adds up to the stock', function () {
    $this->actingAs($this->admin)->post('/inventory', ['chemical_name' => 'Soda Ash', 'unit' => 'lbs', 'current_stock' => 20])->assertRedirect();
    $item = ChemicalInventory::query()->where('chemical_name', 'Soda Ash')->sole();

    foreach ([['usage', 25], ['restock', 4], ['adjustment', 3]] as [$type, $quantity]) {
        $this->actingAs($this->admin)->post("/inventory/{$item->id}/adjust", ['type' => $type, 'quantity' => $quantity])->assertRedirect();
    }

    // Starting stock +20, used 20 of the 25 (that's all there was), +4, counted 3 (−1).
    expect($item->transactions()->orderBy('id')->pluck('quantity')->map(fn ($q) => (float) $q)->all())->toBe([20.0, -20.0, 4.0, -1.0])
        ->and((float) $item->fresh()?->current_stock)->toBe(3.0)
        ->and((float) $item->transactions()->sum('quantity'))->toBe(3.0);
});

test('editing a chemical leaves fields it did not send alone', function () {
    $item = ChemicalInventory::factory()->for($this->tenant)->create(['supplier' => 'Pool Supply Co', 'is_active' => true]);

    $this->actingAs($this->admin)->patch("/inventory/{$item->id}", ['chemical_name' => 'Renamed', 'unit' => 'gal'])->assertRedirect();

    expect($item->fresh()?->supplier)->toBe('Pool Supply Co')
        ->and($item->fresh()?->is_active)->toBeTrue();
});

test('archiving takes a chemical off the list but keeps its history, and restoring brings it back', function () {
    $item = ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Cal Hypo', 'current_stock' => 10]);
    $item->move('restock', 5);

    $this->actingAs($this->admin)->post("/inventory/{$item->id}/archive")->assertRedirect();

    expect($item->fresh()?->is_active)->toBeFalse()
        ->and($item->transactions()->count())->toBe(1);
    $this->actingAs($this->admin)->get('/inventory')
        ->assertInertia(fn (Assert $page) => $page->has('items.data', 0)->where('counts.active', 0)->where('counts.archived', 1));
    $this->actingAs($this->admin)->get("/inventory?archived=1&selected={$item->id}")
        ->assertInertia(fn (Assert $page) => $page->where('items.data.0.name', 'Cal Hypo')->where('selected.is_active', false));

    $this->actingAs($this->admin)->post("/inventory/{$item->id}/restore")->assertRedirect();

    expect($item->fresh()?->is_active)->toBeTrue();
});

test('agents cannot manage inventory', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();
    $item = ChemicalInventory::factory()->for($this->tenant)->create();

    $this->actingAs($agent)->post('/inventory', ['chemical_name' => 'X', 'unit' => 'gal'])->assertForbidden();
    $this->actingAs($agent)->post("/inventory/{$item->id}/archive")->assertForbidden();
    $this->actingAs($agent)->post("/inventory/{$item->id}/restore")->assertForbidden();
    expect($item->fresh()?->is_active)->toBeTrue();
});
