<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\InventoryTransaction;
use App\Models\ServiceVisit;

/**
 * Undo a visit's inventory deductions: each chemical it used goes back on the
 * shelf and its usage entries are removed. Run before a visit is re-submitted
 * (so an edit never double-deducts) and when its report is deleted.
 */
class ReverseVisitInventory
{
    public function handle(ServiceVisit $visit): void
    {
        $usages = InventoryTransaction::query()
            ->with('inventory')
            ->where('service_visit_id', $visit->id)
            ->where('type', 'usage')
            ->get();

        foreach ($usages as $usage) {
            // Usage is logged as what actually left the shelf (a negative change), so this restores it exactly.
            $usage->inventory?->increment('current_stock', -(float) $usage->quantity);
            $usage->delete();
        }
    }
}
