<?php

declare(strict_types=1);

use App\Models\ChemicalInventory;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('lists this tenant\'s active chemicals with a low-stock flag', function () {
    ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Soda Ash', 'current_stock' => 50, 'reorder_threshold' => 10]);
    ChemicalInventory::factory()->low()->for($this->tenant)->create(['chemical_name' => 'Liquid Chlorine']);

    $other = Tenant::factory()->create();
    ChemicalInventory::factory()->for($other)->create();

    $this->actingAs($this->admin)
        ->get('/inventory')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/Index')
            ->has('items.data', 2)
            ->where('items.data', fn ($items) => collect($items)->firstWhere('name', 'Liquid Chlorine')['low'] === true)
        );
});

test('the drawer computes stock value', function () {
    $item = ChemicalInventory::factory()->for($this->tenant)->create([
        'chemical_name' => 'Cal Hypo', 'current_stock' => 10, 'cost_per_unit' => 2.5,
    ]);

    $this->actingAs($this->admin)
        ->get("/inventory?selected={$item->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.id', $item->id)
            ->where('selected.value', 25)
        );
});

test('customers are denied the Inventory screen', function () {
    $portalUser = User::factory()->customer()->for($this->tenant)->create();

    $this->actingAs($portalUser)->get('/inventory')->assertForbidden();
});
