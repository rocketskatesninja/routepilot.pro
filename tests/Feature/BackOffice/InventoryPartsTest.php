<?php

declare(strict_types=1);

use App\Models\ChemicalInventory;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Inventory holds repair parts as well as chemicals: a part is counted in units,
 * can carry its part number, and is listed on its own Parts tab.
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('a part is counted in units and keeps its part number; a chemical has none', function () {
    $this->actingAs($this->admin)->post('/inventory', ['chemical_name' => 'Pump lid O-ring', 'kind' => 'part', 'part_number' => 'R172009', 'unit' => 'gal', 'current_stock' => 6])
        ->assertSessionHas('success', 'Part added.');
    $this->actingAs($this->admin)->post('/inventory', ['chemical_name' => 'Clarifier', 'unit' => 'gal', 'part_number' => 'X1'])
        ->assertSessionHas('success', 'Chemical added.');

    expect(ChemicalInventory::query()->where('chemical_name', 'Pump lid O-ring')->sole()->only(['kind', 'unit', 'part_number']))
        ->toBe(['kind' => 'part', 'unit' => 'unit', 'part_number' => 'R172009'])
        ->and((float) ChemicalInventory::query()->where('chemical_name', 'Pump lid O-ring')->sole()->current_stock)->toBe(6.0)
        ->and(ChemicalInventory::query()->where('chemical_name', 'Clarifier')->sole()->only(['kind', 'part_number']))->toBe(['kind' => 'chemical', 'part_number' => null]);
});

test('chemicals and parts are listed apart, a part is found by its number, and a link opens its own list', function () {
    $part = ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Pump lid O-ring', 'kind' => 'part', 'part_number' => 'R172009', 'unit' => 'unit']);
    ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Skimmer weir', 'kind' => 'part', 'unit' => 'unit']);
    ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Soda Ash', 'unit' => 'lbs']);

    $names = function (string $url): array {
        $out = [];
        $this->actingAs($this->admin)->get($url)->assertInertia(function (Assert $page) use (&$out) {
            $out = array_column($page->toArray()['props']['items']['data'], 'name');
        });

        return $out;
    };

    expect($names('/inventory'))->toBe(['Soda Ash'])
        ->and($names('/inventory?type=parts'))->toBe(['Pump lid O-ring', 'Skimmer weir'])
        ->and($names('/inventory?type=parts&search=1720'))->toBe(['Pump lid O-ring'])
        ->and($names('/inventory?selected='.$part->id))->toBe(['Pump lid O-ring', 'Skimmer weir']);

    $this->actingAs($this->admin)->get('/inventory?type=parts')->assertInertia(fn (Assert $page) => $page
        ->where('counts.chemicals', 1)->where('counts.parts', 2)->where('counts.active', 2)->where('filters.type', 'parts'));
});

test('a part stays a part when edited, and its number can change; a chemical’s can’t be given one', function () {
    $part = ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'O-ring', 'kind' => 'part', 'unit' => 'unit']);
    $soda = ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Soda Ash', 'unit' => 'lbs']);

    $this->actingAs($this->admin)->patch("/inventory/{$part->id}", ['chemical_name' => 'Pump lid O-ring', 'kind' => 'chemical', 'unit' => 'unit', 'part_number' => 'R172009'])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->patch("/inventory/{$soda->id}", ['chemical_name' => 'Soda Ash', 'unit' => 'lbs', 'part_number' => 'X1', 'kind' => 'part'])
        ->assertSessionHasNoErrors();

    expect($part->fresh()?->only(['chemical_name', 'kind', 'part_number']))->toBe(['chemical_name' => 'Pump lid O-ring', 'kind' => 'part', 'part_number' => 'R172009'])
        ->and($soda->fresh()?->only(['kind', 'part_number']))->toBe(['kind' => 'chemical', 'part_number' => null]);
});
