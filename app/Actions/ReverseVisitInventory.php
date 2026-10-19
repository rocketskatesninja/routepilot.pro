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
            ->where('service_visit_id', $visit->id)
            ->where('type', 'usage')
            ->get();

        foreach ($usages as $usage) {
            $item = $usage->inventory()->first();
            if ($item !== null) {
                // quantity was stored negative (e.g. -32); subtracting it restores stock.
                $item->update(['current_stock' => (float) $item->current_stock - (float) $usage->quantity]);
            }
            $usage->delete();
        }
    }
}
