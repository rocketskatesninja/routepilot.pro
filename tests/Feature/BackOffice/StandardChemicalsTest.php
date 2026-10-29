<?php

declare(strict_types=1);

use App\Models\ChemicalInventory;
use App\Models\Tenant;
use App\Models\User;
use App\Support\StandardChemicals;

/*
 * Every company starts with the common pool chemicals, each in its one unit, and
 * a chemical is only ever kept in gallons, pounds, 40-lb bags, or counted pucks/tabs.
 */

test('a new company starts with the standard chemicals, in gallons or pounds and with nothing in stock', function () {
    $this->post('/register', [
        'company' => 'Sunshine Pools', 'first_name' => 'Sam', 'last_name' => 'Rivera',
        'email' => 'sam@example.com', 'password' => 'password', 'password_confirmation' => 'password',
    ])->assertRedirect();

    $tenant = Tenant::query()->where('name', 'Sunshine Pools')->sole();
    $chemicals = ChemicalInventory::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->get();

    expect($chemicals->pluck('standard_key')->sort()->values()->all())->toBe(collect(array_keys(StandardChemicals::LIST))->sort()->values()->all())
        ->and($chemicals->firstWhere('standard_key', 'muriatic_acid')?->only(['chemical_name', 'unit']))->toBe(['chemical_name' => 'Muriatic Acid', 'unit' => 'gal'])
        ->and($chemicals->firstWhere('standard_key', 'cal_hypo')?->unit)->toBe('lbs')
        ->and($chemicals->firstWhere('standard_key', 'salt')?->unit)->toBe('bag') // by the 40-lb bag
        ->and($chemicals->firstWhere('standard_key', 'chlorine_pucks')?->only(['chemical_name', 'unit']))->toBe(['chemical_name' => 'Chlorine Pucks', 'unit' => 'puck'])
        ->and($chemicals->firstWhere('standard_key', 'chlorine_tabs')?->only(['chemical_name', 'unit']))->toBe(['chemical_name' => 'Chlorine Tabs', 'unit' => 'tab'])
        ->and($chemicals->every(fn (ChemicalInventory $c): bool => (float) $c->current_stock === 0.0 && $c->is_active))->toBeTrue();
});

test('adding the standard list only adds what a company is missing, even after a rename', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $tenant->id);
    $renamed = ChemicalInventory::factory()->for($tenant)->create(['chemical_name' => 'Cal-Hypo 73%', 'unit' => 'lbs']);
    $renamed->forceFill(['standard_key' => 'cal_hypo'])->save();

    StandardChemicals::addTo($tenant->id);
    StandardChemicals::addTo($tenant->id); // twice changes nothing

    expect(ChemicalInventory::query()->count())->toBe(count(StandardChemicals::LIST))
        ->and(ChemicalInventory::query()->where('standard_key', 'cal_hypo')->sole()->chemical_name)->toBe('Cal-Hypo 73%');
});

test('a chemical is kept in gallons, pounds, bags, pucks or tabs only', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->for($tenant)->create();

    $this->actingAs($admin)->post('/inventory', ['chemical_name' => 'Clarifier', 'unit' => 'oz'])->assertSessionHasErrors('unit');
    $this->actingAs($admin)->post('/inventory', ['chemical_name' => 'Clarifier', 'unit' => 'gal'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/inventory', ['chemical_name' => 'Salt', 'unit' => 'bag'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/inventory', ['chemical_name' => 'Trichlor 3"', 'unit' => 'puck'])->assertSessionHasNoErrors();
});

test('a chemical\'s unit can\'t change once it has stock or history', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $tenant->id);
    $admin = User::factory()->for($tenant)->create();
    $fresh = ChemicalInventory::factory()->for($tenant)->create(['chemical_name' => 'Salt', 'unit' => 'lbs', 'current_stock' => 0]);
    $stocked = ChemicalInventory::factory()->for($tenant)->create(['chemical_name' => 'Cal Hypo', 'unit' => 'lbs', 'current_stock' => 0]);
    $stocked->move('restock', 25);
    $edit = fn (ChemicalInventory $c, string $unit) => $this->actingAs($admin)->patch("/inventory/{$c->id}", ['chemical_name' => $c->chemical_name, 'unit' => $unit]);

    $edit($fresh, 'bag')->assertSessionHasNoErrors();
    $edit($stocked, 'bag')->assertSessionHasErrors(['unit' => 'The unit can’t change once a chemical has stock or history. Add a new chemical for the new unit.']);
    $edit($stocked, 'lbs')->assertSessionHasNoErrors(); // keeping its unit is fine

    expect($fresh->fresh()?->unit)->toBe('bag')->and($stocked->fresh()?->unit)->toBe('lbs');
    $this->actingAs($admin)->get("/inventory?selected={$stocked->id}")->assertInertia(fn ($page) => $page->where('selected.unit_locked', true));
});

test('amounts read naturally with their unit', function () {
    expect(StandardChemicals::amount(1, 'bag'))->toBe('1 bag')
        ->and(StandardChemicals::amount(2, 'bag'))->toBe('2 bags')
        ->and(StandardChemicals::amount(1, 'lbs'))->toBe('1 lb')
        ->and(StandardChemicals::amount(2.5, 'lbs'))->toBe('2.5 lbs')
        ->and(StandardChemicals::amount(3, 'gal'))->toBe('3 gal')
        ->and(StandardChemicals::amount(9.6, 'oz'))->toBe('9.6 oz')
        ->and(StandardChemicals::amount(1, 'puck'))->toBe('1 puck')
        ->and(StandardChemicals::amount(3, 'puck'))->toBe('3 pucks')
        ->and(StandardChemicals::amount(2, 'tab'))->toBe('2 tabs')
        ->and(StandardChemicals::unitFor(1, 'bag'))->toBe('bag');
});

test('existing companies get pucks and tabs once, linked to a same-named one they already keep', function () {
    $migration = require database_path('migrations/2026_11_07_000000_chlorine_pucks_and_tabs.php');
    $plain = Tenant::factory()->create();
    $own = Tenant::factory()->create();
    $mine = ChemicalInventory::factory()->for($own)->create(['chemical_name' => 'chlorine tabs', 'unit' => 'tab', 'current_stock' => 40]);

    $migration->up();
    $migration->up(); // and again changes nothing

    $keys = fn (Tenant $t) => ChemicalInventory::query()->withoutGlobalScopes()->where('tenant_id', $t->id)->pluck('standard_key', 'chemical_name')->all();
    expect($keys($plain))->toBe(['Chlorine Pucks' => 'chlorine_pucks', 'Chlorine Tabs' => 'chlorine_tabs'])
        ->and($keys($own))->toBe(['chlorine tabs' => 'chlorine_tabs', 'Chlorine Pucks' => 'chlorine_pucks'])
        ->and((float) $mine->fresh()?->current_stock)->toBe(40.0);
});

test('the field app gets the chemicals still in use, with the standard key suggestions match on', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $tenant->id);
    $agent = User::factory()->agent()->for($tenant)->create();
    StandardChemicals::addTo($tenant->id);
    ChemicalInventory::query()->where('standard_key', 'salt')->update(['is_active' => false]); // archived
    app()->forgetInstance('tenant_id');

    $inventory = $this->actingAs($agent)->getJson('/api/field/today')->assertOk()->json('inventory');

    expect(count($inventory))->toBe(count(StandardChemicals::LIST) - 1)
        ->and(collect($inventory)->firstWhere('key', 'muriatic_acid'))->toMatchArray(['name' => 'Muriatic Acid', 'unit' => 'gal'])
        ->and(collect($inventory)->pluck('key'))->not->toContain('salt');
});
