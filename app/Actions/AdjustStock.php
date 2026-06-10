<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ChemicalInventory;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Move stock and log it: restock (+), usage (−), or adjustment (set to an
 * exact count). The transaction records the signed delta.
 */
class AdjustStock
{
    /**
     * @param  array<string, mixed>  $data  validated {type, quantity, notes}
     */
    public function handle(ChemicalInventory $chemical, array $data, int $userId): InventoryTransaction
    {
        return DB::transaction(function () use ($chemical, $data, $userId): InventoryTransaction {
            $type = (string) $data['type'];
            $quantity = (float) $data['quantity'];
            $current = (float) $chemical->current_stock;

            $delta = match ($type) {
                'restock' => $quantity,
                'usage' => -$quantity,
                'adjustment' => $quantity - $current, // set to the given count
                default => 0.0,
            };

            $chemical->update(['current_stock' => max(0.0, $current + $delta)]);

            return InventoryTransaction::create([
                'chemical_inventory_id' => $chemical->id,
                'type' => $type,
                'quantity' => $delta,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);
        });
    }
}
