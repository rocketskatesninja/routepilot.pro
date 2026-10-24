<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ChemicalInventory;
use App\Services\PhotoService;
use Illuminate\Support\Arr;

/**
 * Update a chemical's details. Stock level changes go through AdjustStock so
 * every movement is logged.
 */
class UpdateChemical
{
    public function __construct(private readonly PhotoService $photos) {}

    /**
     * @param  array<string, mixed>  $data  validated UpdateChemicalRequest fields
     */
    public function handle(ChemicalInventory $chemical, array $data): ChemicalInventory
    {
        $chemical->update(Arr::except($data, 'photo'));

        $this->photos->attach($chemical, $data['photo'] ?? null, 'photo_path', 'inventory');

        return $chemical;
    }
}
