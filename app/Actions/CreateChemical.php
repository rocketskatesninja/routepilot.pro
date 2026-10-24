<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ChemicalInventory;
use App\Services\PhotoService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Add a chemical to the tenant's central stock. Its starting stock is logged
 * as the first restock, so the history adds up from day one.
 */
class CreateChemical
{
    public function __construct(private readonly PhotoService $photos) {}

    /**
     * @param  array<string, mixed>  $data  validated StoreChemicalRequest fields
     */
    public function handle(array $data, int $userId): ChemicalInventory
    {
        $chemical = DB::transaction(function () use ($data, $userId): ChemicalInventory {
            $chemical = ChemicalInventory::create(Arr::except($data, ['current_stock', 'photo']));
            $start = (float) ($data['current_stock'] ?? 0);
            if ($start > 0) {
                $chemical->move('restock', $start, ['created_by' => $userId]);
            }

            return $chemical;
        });

        $this->photos->attach($chemical, $data['photo'] ?? null, 'photo_path', 'inventory');

        return $chemical;
    }
}
