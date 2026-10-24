<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ChemicalInventory;
use App\Models\InventoryTransaction;
use InvalidArgumentException;

/**
 * Update stock from the back office: restocked (+), used (−), or counted (set
 * to the amount on the shelf).
 */
class AdjustStock
{
    /**
     * @param  array<string, mixed>  $data  validated {type, quantity}
     */
    public function handle(ChemicalInventory $chemical, array $data, int $userId): InventoryTransaction
    {
        $type = (string) $data['type'];
        $quantity = (float) $data['quantity'];

        $change = match ($type) {
            'restock' => $quantity,
            'usage' => -$quantity,
            'adjustment' => $quantity - (float) $chemical->current_stock,
            default => throw new InvalidArgumentException("Unknown stock change: {$type}"),
        };

        return $chemical->move($type, $change, ['created_by' => $userId]);
    }
}
