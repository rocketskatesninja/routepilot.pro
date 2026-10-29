<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\ChemicalInventory;
use App\Services\Chat\AiTool;
use App\Support\StandardChemicals;

/**
 * Check chemical inventory stock levels — a specific chemical or all items
 * with low-stock flags.
 */
class LookupInventory extends AiTool
{
    public function name(): string
    {
        return 'lookup_inventory';
    }

    public function description(): string
    {
        return 'Check chemical inventory stock levels. '
            .'Use when asked about inventory, stock, what chemicals are available, or what\'s running low.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'chemical_name' => ['type' => 'string', 'description' => 'Chemical name to search for (optional — omit to list all inventory)'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $params, int $tenantId): string
    {
        $search = trim((string) ($params['chemical_name'] ?? ''));

        $items = ChemicalInventory::query()
            ->active()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('chemical_name', 'like', "%{$search}%")->orWhere('part_number', 'like', "%{$search}%")))
            ->orderBy('chemical_name')
            ->get();

        if ($items->isEmpty()) {
            $what = $search !== '' ? " matching \"{$search}\"" : '';

            return "No inventory items found{$what}.";
        }

        $rows = $items->map(function (ChemicalInventory $item): string {
            $stock = StandardChemicals::amount((float) $item->current_stock, $item->unit);
            $threshold = $item->reorder_threshold !== null ? StandardChemicals::amount((float) $item->reorder_threshold, $item->unit) : 'none set';
            $low = $item->isLowStock() ? ' ⚠️ LOW STOCK' : '';
            $cost = $item->cost_per_unit !== null ? '$'.number_format((float) $item->cost_per_unit, 2).'/'.StandardChemicals::unitFor(1, $item->unit) : '';
            $supplier = $item->supplier !== null ? " (from {$item->supplier})" : '';

            $part = $item->isPart() ? ' (repair part'.($item->part_number !== null ? ", #{$item->part_number}" : '').')' : '';

            return "- **{$item->chemical_name}**{$part} [id:{$item->id}]: {$stock} (reorder at: {$threshold}){$low}"
                .($cost !== '' ? "\n  Cost: {$cost}{$supplier}" : '');
        })->join("\n");

        $lowCount = $items->filter(fn (ChemicalInventory $i): bool => $i->isLowStock())->count();
        $summary = $lowCount > 0 ? "\n\n⚠️ {$lowCount} item(s) at or below reorder threshold." : '';

        return "Inventory ({$items->count()} items):\n\n{$rows}{$summary}";
    }
}
