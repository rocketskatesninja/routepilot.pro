<?php

declare(strict_types=1);

use App\Models\ChemicalInventory;
use App\Models\Tenant;
use App\Support\DemoParts;

/*
 * The one-time migration fills only the demo company's Parts tab (real companies
 * start with no parts), and never adds a part it already has.
 */

test('only the demo company gets the parts, once, keeping one it already had', function () {
    $migration = require database_path('migrations/2026_11_10_000000_demo_company_parts.php');
    $demo = Tenant::factory()->create(['slug' => 'demo']);
    $real = Tenant::factory()->create(['slug' => 'glynn']);
    $own = ChemicalInventory::factory()->for($demo)->create(['chemical_name' => 'pump lid o-ring', 'kind' => 'part', 'unit' => 'unit', 'current_stock' => 0]);

    $migration->up();
    $migration->up();

    $parts = fn (Tenant $t) => ChemicalInventory::withoutGlobalScopes()->where('tenant_id', $t->id)->where('kind', 'part');
    expect($parts($demo)->count())->toBe(count(DemoParts::LIST))
        ->and($parts($demo)->where('chemical_name', 'like', 'pump lid o-ring')->count())->toBe(1)
        ->and((float) $own->fresh()?->current_stock)->toBe(0.0)
        ->and($parts($real)->exists())->toBeFalse()
        ->and(app()->has('tenant_id'))->toBeFalse();
});
