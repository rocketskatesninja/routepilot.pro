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
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Back-office Inventory screen — central chemical stock as a list + detail
 * pane. The pane shows value, reorder status, and recent stock changes.
 */
class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeStaff($request);

        $search = trim((string) $request->string('search'));
        $archived = $request->boolean('archived');

        $query = ChemicalInventory::query()
            ->where('is_active', ! $archived)
            ->when($search !== '', fn ($q) => $q->where('chemical_name', 'like', '%'.$search.'%'));

        $sort = $this->applySort($query, $request, [
            'name' => 'chemical_name',
            'stock' => 'current_stock',
            'cost' => 'cost_per_unit',
        ], 'name');

        $items = $query
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (ChemicalInventory $i) => [
                'id' => $i->id,
                'name' => $i->chemical_name,
                'photo_url' => $this->photoUrl($i->getAttribute('photo_path')),
                'unit' => $i->unit,
                'stock' => (float) $i->current_stock,
                'low' => $i->isLowStock(),
                'cost_per_unit' => self::number($i->cost_per_unit),
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
            'filters' => ['search' => $search, 'archived' => $archived],
            'counts' => [
                'active' => ChemicalInventory::query()->active()->count(),
                'archived' => ChemicalInventory::query()->where('is_active', false)->count(),
            ],
            'sort' => $sort,
            'canManage' => $this->canManage($request->user()),
        ]);
    }

    public function store(StoreChemicalRequest $request, CreateChemical $action): RedirectResponse
    {
        $action->handle($request->validated(), (int) $request->user()?->id);

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

        return back()->with('success', 'Stock updated.');
    }

    /** Off the list and out of visit deductions; its history stays, and it can be restored. */
    public function archive(Request $request, ChemicalInventory $chemical): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $chemical->update(['is_active' => false]);

        return back()->with('success', 'Chemical archived.');
    }

    public function restore(Request $request, ChemicalInventory $chemical): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $chemical->update(['is_active' => true]);

        return back()->with('success', 'Chemical restored.');
    }

    /** @return array<string, mixed> */
    private function toDetail(ChemicalInventory $item): array
    {
        $stock = (float) $item->current_stock;
        $cost = self::number($item->cost_per_unit);

        $transactions = $item->transactions()
            ->with(['agent:id,first_name,last_name,role', 'creator:id,first_name,last_name', 'pool' => fn ($q) => $q->withTrashed()])
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (InventoryTransaction $t) => [
                'id' => $t->id,
                'type' => $t->getAttribute('type'),
                'change' => (float) $t->quantity, // signed: + onto the shelf, − off it
                'on' => Tenant::localDate($t->created_at),
                // The tech on a visit; otherwise whoever updated the stock in the office.
                'by' => ($t->agent ?? $t->creator)?->displayName(),
                'agent_id' => $t->agent?->role === 'agent' ? $t->agent->id : null, // People opens techs only
                // Usage from a visit: the pool, and the report it's on (an archived pool isn't on the Pools page, so no link).
                'pool' => $t->pool?->getAttribute('name'),
                'pool_id' => $t->pool !== null && ! $t->pool->trashed() ? $t->pool->id : null,
                'report_id' => $t->getAttribute('service_visit_id'),
            ])->all();

        return [
            'id' => $item->id,
            'name' => $item->chemical_name,
            'photo_url' => $this->photoUrl($item->getAttribute('photo_path')),
            'unit' => $item->unit,
            'stock' => $stock,
            'reorder_threshold' => self::number($item->reorder_threshold),
            'cost_per_unit' => $cost,
            'sell_price' => self::number($item->sell_price),
            'supplier' => $item->supplier,
            'is_active' => $item->is_active,
            'unit_locked' => $item->unitLocked(), // the edit form can't change it then
            'value' => $cost !== null ? round($stock * $cost, 2) : null,
            'low' => $item->isLowStock(),
            'transactions' => $transactions,
        ];
    }

    /** A decimal column as a number for the page (decimal casts come back as strings). */
    private static function number(mixed $value): ?float
    {
        return $value !== null ? (float) $value : null;
    }
}
