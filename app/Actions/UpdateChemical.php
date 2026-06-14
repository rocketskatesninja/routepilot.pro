<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ChemicalInventory;
use App\Services\PhotoService;

/**
 * Update a chemical's details. Stock level changes go through AdjustStock so
 * every movement is logged.
 */
class UpdateChemical
{
    public function __construct(private readonly PhotoService $photos) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(ChemicalInventory $chemical, array $data): ChemicalInventory
    {
        $chemical->update([
            'chemical_name' => $data['chemical_name'],
            'unit' => $data['unit'],
            'reorder_threshold' => $data['reorder_threshold'] ?? null,
            'cost_per_unit' => $data['cost_per_unit'] ?? null,
            'sell_price' => $data['sell_price'] ?? null,
            'supplier' => $data['supplier'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        $this->photos->attach($chemical, $data['photo'] ?? null, 'photo_path', 'inventory');

        return $chemical;
    }
}
