<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ChemicalTreatment — a chemical or repair part used on a visit: the inventory
 * item it was picked from, with its name and unit as they were then, and the
 * whole amount. Older ones were typed and point at no item (always chemicals).
 * RecordTreatments writes them.
 */
class ChemicalTreatment extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['service_visit_id', 'chemical_inventory_id', 'chemical_name', 'amount', 'unit'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'float'];
    }

    /** @return BelongsTo<ChemicalInventory, $this> */
    public function chemical(): BelongsTo
    {
        return $this->belongsTo(ChemicalInventory::class, 'chemical_inventory_id');
    }

    /** A repair part rather than a chemical (eager-load `chemical:id,kind` when splitting a list). */
    public function isPart(): bool
    {
        return $this->chemical?->isPart() === true;
    }
}
