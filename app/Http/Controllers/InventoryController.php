<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ChemicalInventory;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Back-office Inventory screen — central chemical stock on the table+drawer
 * pattern. The drawer shows value, reorder status, and recent stock moves.
 */
class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeStaff($request);

        $search = trim((string) $request->string('search'));

        $items = ChemicalInventory::query()
            ->where('is_active', true)
            ->when($search !== '', fn ($q) => $q->where('chemical_name', 'like', '%'.$search.'%'))
            ->orderBy('chemical_name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ChemicalInventory $i) => [
                'id' => $i->id,
                'name' => $i->chemical_name,
                'unit' => $i->unit,
                'stock' => (float) $i->current_stock,
                'low' => $i->isLowStock(),
                'cost_per_unit' => $i->cost_per_unit !== null ? (float) $i->cost_per_unit : null,
            ]);

        $selected = null;
        $selectedId = $request->integer('selected');
        if ($selectedId > 0) {
            $item = ChemicalInventory::query()->find($selectedId);
            if ($item !== null) {
                $selected = $this->toDetail($item);
            }
        }

        return Inertia::render('inventory/Index', [
            'items' => $items,
            'selected' => $selected,
            'filters' => ['search' => $search],
        ]);
    }

    private function authorizeStaff(Request $request): void
    {
        $user = $request->user();
        abort_unless($user !== null && $user->tenant_id !== null && in_array($user->role, ['tenant_admin', 'agent'], true), 403);
    }

    /** @return array<string, mixed> */
    private function toDetail(ChemicalInventory $item): array
    {
        $stock = (float) $item->current_stock;
        $cost = $item->cost_per_unit !== null ? (float) $item->cost_per_unit : null;

        $transactions = InventoryTransaction::query()
            ->where('chemical_inventory_id', $item->id)
            ->with('agent:id,first_name,last_name')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (InventoryTransaction $t) => [
                'id' => $t->id,
                'type' => $t->getAttribute('type'),
                'quantity' => (float) $t->quantity,
                'on' => $t->created_at?->toDateString(),
                'agent' => $t->agent?->displayName(),
            ])->all();

        return [
            'id' => $item->id,
            'name' => $item->chemical_name,
            'unit' => $item->unit,
            'stock' => $stock,
            'reorder_threshold' => $item->reorder_threshold !== null ? (float) $item->reorder_threshold : null,
            'cost_per_unit' => $cost,
            'sell_price' => $item->sell_price !== null ? (float) $item->sell_price : null,
            'supplier' => $item->supplier,
            'value' => $cost !== null ? round($stock * $cost, 2) : null,
            'low' => $item->isLowStock(),
            'transactions' => $transactions,
        ];
    }
}
