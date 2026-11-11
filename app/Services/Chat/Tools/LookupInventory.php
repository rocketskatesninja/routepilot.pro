<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\ChemicalInventory;
use App\Models\User;
use App\Services\Chat\AssistantTool;
use App\Support\StandardChemicals;

/** Office, when the company counts its stock: stock levels, low items first. */
class LookupInventory extends AssistantTool
{
    public function name(): string
    {
        return 'lookup_inventory';
    }

    public function description(): string
    {
        return 'Check chemical and repair-part stock: how much is left, the reorder level, cost and supplier. Low items '
            .'come first. Leave out the name for everything.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'chemical_name' => ['type' => 'string', 'description' => 'A chemical or part name or number (optional).'],
            ],
            'required' => [],
        ];
    }

    public function availableTo(User $user): bool
    {
        return $user->role === 'tenant_admin' && $this->tenant($user)?->inventory_tracking !== false;
    }

    public function execute(array $params, User $user): string
    {
        $search = trim((string) ($params['chemical_name'] ?? ''));
        $items = ChemicalInventory::query()
            ->where('tenant_id', (int) $user->tenant_id)
            ->active()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('chemical_name', 'like', "%{$search}%")->orWhere('part_number', 'like', "%{$search}%")))
            ->orderBy('chemical_name')
            ->get()
            ->sortByDesc(fn (ChemicalInventory $i): bool => $i->isLowStock())
            ->values();

        if ($items->isEmpty()) {
            return 'No stock items found'.($search !== '' ? " matching \"{$search}\"" : '').'.';
        }

        $shown = $items->take(self::ROWS);
        $low = $items->filter(fn (ChemicalInventory $i): bool => $i->isLowStock())->count();

        return "Stock ({$items->count()} items".($low > 0 ? ", {$low} low" : '')."):\n".$shown->map(function (ChemicalInventory $item): string {
            $stock = StandardChemicals::amount((float) $item->current_stock, $item->unit);
            $reorder = $item->reorder_threshold !== null ? StandardChemicals::amount((float) $item->reorder_threshold, $item->unit) : 'not set';
            $cost = $item->cost_per_unit !== null ? ', $'.number_format((float) $item->cost_per_unit, 2).'/'.StandardChemicals::unitFor(1, $item->unit) : '';

            return '- '.self::link('inventory', $item->id, $item->chemical_name)
                .($item->isPart() ? ' (repair part'.($item->part_number !== null ? " #{$item->part_number}" : '').')' : '')
                .": {$stock} (reorder at {$reorder})".($item->isLowStock() ? ' LOW' : '')
                .$cost.($item->supplier !== null ? ", from {$item->supplier}" : '');
        })->join("\n").self::more($items->count(), $shown->count());
    }
}
