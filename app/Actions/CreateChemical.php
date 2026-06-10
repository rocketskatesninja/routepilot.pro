<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ChemicalInventory;

/**
 * Add a chemical to the tenant's central stock.
 */
class CreateChemical
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): ChemicalInventory
    {
        return ChemicalInventory::create([
            'chemical_name' => $data['chemical_name'],
            'unit' => $data['unit'],
            'current_stock' => $data['current_stock'] ?? 0,
            'reorder_threshold' => $data['reorder_threshold'] ?? null,
            'cost_per_unit' => $data['cost_per_unit'] ?? null,
            'sell_price' => $data['sell_price'] ?? null,
            'supplier' => $data['supplier'] ?? null,
            'is_active' => true,
        ]);
    }
}
