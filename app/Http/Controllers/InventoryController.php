<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AdjustStock;
use App\Actions\CreateChemical;
use App\Actions\UpdateChemical;
use App\Http\Requests\AdjustStockRequest;
use App\Http\Requests\StoreChemicalRequest;
use App\Http\Requests\UpdateChemicalRequest;
use App\Models\ChemicalInventory;
use App\Models\InventoryTransaction;
use Illuminate\Http\RedirectResponse;
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
                'photo_url' => $this->photoUrl($i->getAttribute('photo_path')),
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
            'canManage' => $request->user()?->role === 'tenant_admin',
        ]);
    }

    public function store(StoreChemicalRequest $request, CreateChemical $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'Chemical added.');
    }

    public function update(UpdateChemicalRequest $request, ChemicalInventory $chemical, UpdateChemical $action): RedirectResponse
    {
        $action->handle($chemical, $request->validated());

        return back()->with('success', 'Chemical updated.');
    }

    public function adjust(AdjustStockRequest $request, ChemicalInventory $chemical, AdjustStock $action): RedirectResponse
    {
        $action->handle($chemical, $request->validated(), (int) $request->user()?->id);

        return back()->with('success', 'Stock adjusted.');
    }

    public function destroy(Request $request, ChemicalInventory $chemical): RedirectResponse
    {
        abort_unless($request->user()?->role === 'tenant_admin', 403);
        $chemical->delete();

        return back()->with('success', 'Chemical removed.');
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
                'agent_id' => $t->agent?->getKey(),
            ])->all();

        return [
            'id' => $item->id,
            'name' => $item->chemical_name,
            'photo_url' => $this->photoUrl($item->getAttribute('photo_path')),
            'unit' => $item->unit,
            'stock' => $stock,
            'reorder_threshold' => $item->reorder_threshold !== null ? (float) $item->reorder_threshold : null,
            'cost_per_unit' => $cost,
            'sell_price' => $item->sell_price !== null ? (float) $item->sell_price : null,
            'supplier' => $item->supplier,
            'value' => $cost !== null ? round($stock * $cost, 2) : null,
            'low' => $item->isLowStock(),
            'transactions' => $transactions,
            // Raw values for the edit form.
            'fields' => [
                'chemical_name' => $item->chemical_name,
                'unit' => $item->unit,
                'reorder_threshold' => $item->reorder_threshold !== null ? (float) $item->reorder_threshold : null,
                'cost_per_unit' => $cost,
                'sell_price' => $item->sell_price !== null ? (float) $item->sell_price : null,
                'supplier' => $item->supplier,
                'is_active' => $item->is_active,
            ],
        ];
    }
}
