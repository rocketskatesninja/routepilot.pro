<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ChemicalTreatment — a chemical used on a visit: the company chemical it was
 * picked from, with its name and unit as they were then, and the whole amount.
 * Older ones were typed and point at no chemical. RecordTreatments writes them.
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
}
