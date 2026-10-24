<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ChemicalInventory;
use App\Models\ServiceVisit;
use App\Services\BillingService;

/**
 * Record the chemicals used on a visit — from the field app or an office edit,
 * the same way. Whatever the visit took off the shelf before goes back; then
 * each chemical picked from the company's list is recorded in its own unit,
 * rounded up to a whole one, taken off the shelf, and billed if the customer
 * pays for chemicals.
 *
 * A row with no chemical picked but a name was typed: by a copy of the field
 * app from before the list (still in a phone's offline queue), or on an older
 * report. It's kept as typed, and isn't taken from stock or billed.
 */
class RecordTreatments
{
    public function __construct(
        private readonly ReverseVisitInventory $reverseInventory,
        private readonly BillingService $billing,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows  {chemical_id, amount} or a typed {name, amount, unit}
     */
    public function handle(ServiceVisit $visit, array $rows, int $byUserId): void
    {
        $this->reverseInventory->handle($visit);
        $visit->treatments()->delete();

        // Archived ones too: an older report can still be edited with what it used.
        $ids = array_filter(array_map(fn (array $r): int => (int) ($r['chemical_id'] ?? 0), $rows));
        $chemicals = ChemicalInventory::query()->whereKey($ids)->get()->keyBy('id');

        foreach ($rows as $row) {
            $chemical = $chemicals->get((int) ($row['chemical_id'] ?? 0));
            $amount = is_numeric($row['amount'] ?? null) ? (float) $row['amount'] : 0.0;

            if ($chemical !== null) {
                $whole = (int) ceil($amount);
                if ($whole < 1) {
                    continue;
                }
                $visit->treatments()->create([
                    'chemical_inventory_id' => $chemical->id,
                    'chemical_name' => $chemical->chemical_name,
                    'amount' => $whole,
                    'unit' => $chemical->unit,
                ]);
                $chemical->move('usage', -$whole, [
                    'service_visit_id' => $visit->id,
                    'agent_id' => $visit->getAttribute('agent_id'),
                    'pool_id' => $visit->getAttribute('pool_id'),
                    'created_by' => $byUserId,
                ]);
            } elseif (is_string($row['name'] ?? null) && trim($row['name']) !== '') {
                $visit->treatments()->create([
                    'chemical_name' => trim($row['name']),
                    'amount' => $amount,
                    'unit' => is_string($row['unit'] ?? null) ? $row['unit'] : '',
                ]);
            }
        }

        $this->billing->chargeChemicals($visit);
    }
}
