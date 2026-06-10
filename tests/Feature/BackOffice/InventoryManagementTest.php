<?php

declare(strict_types=1);

use App\Models\ChemicalInventory;
use App\Models\Tenant;
use App\Models\User;

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
        ->patch("/inventory/{$item->id}", ['chemical_name' => 'New', 'unit' => 'lbs'])
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

test('agents cannot manage inventory', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->post('/inventory', ['chemical_name' => 'X', 'unit' => 'gal'])->assertForbidden();
});
