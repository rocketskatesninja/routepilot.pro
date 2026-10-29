<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ChemicalInventory;

/**
 * Common repair parts for the demo company's Parts tab, so there's something to
 * try: generic names (part numbers depend on the equipment brand), with stock,
 * reorder levels and prices. The skimmer lid starts below its reorder level, to
 * show Running low. Shop supplies have no sell price, so they aren't billed.
 * Real companies start with no parts and add their own.
 */
final class DemoParts
{
    /** name => [stock, reorder at, cost, sell price (null: a shop supply, not billed)] */
    public const LIST = [
        // O-rings and seals
        'Pump lid O-ring' => [8, 3, 6.50, 14.00],
        'Filter tank O-ring' => [4, 2, 9.00, 19.00],
        'Chlorinator lid O-ring' => [5, 2, 5.00, 12.00],
        'Pump shaft seal' => [3, 2, 14.00, 32.00],
        // Baskets and the skimmer
        'Pump strainer basket' => [2, 1, 22.00, 45.00],
        'Skimmer basket' => [4, 2, 12.00, 28.00],
        'Skimmer weir door' => [2, 1, 15.00, 34.00],
        'Skimmer lid' => [1, 2, 18.00, 39.00],
        // Filter and gauges
        'Filter cartridge' => [2, 1, 85.00, 165.00],
        'Pressure gauge' => [3, 1, 9.00, 24.00],
        'Air relief valve' => [2, 1, 11.00, 26.00],
        'Drain plug' => [6, 2, 3.50, 9.00],
        // Fittings and shop supplies
        'Return jet eyeball' => [4, 2, 6.00, 15.00],
        'O-ring lubricant' => [3, 1, 8.00, null],
        'Thread seal tape' => [10, 3, 1.50, null],
    ];

    /**
     * Give the bound company (app('tenant_id')) the demo parts it doesn't have yet,
     * each stocked through move() so its history shows the restock.
     */
    public static function addTo(): void
    {
        $have = ChemicalInventory::query()->parts()->pluck('chemical_name')->map(fn (string $n): string => mb_strtolower($n))->all();

        foreach (self::LIST as $name => [$stock, $reorder, $cost, $sell]) {
            if (in_array(mb_strtolower($name), $have, true)) {
                continue;
            }
            $part = ChemicalInventory::create([
                'chemical_name' => $name, 'kind' => 'part', 'unit' => StandardChemicals::PART_UNIT, 'current_stock' => 0,
                'reorder_threshold' => $reorder, 'cost_per_unit' => $cost, 'sell_price' => $sell, 'is_active' => true,
            ]);
            $part->move('restock', $stock);
        }
    }
}
