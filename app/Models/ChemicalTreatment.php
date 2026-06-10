<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ChemicalTreatment — a chemical applied during a visit (name, amount,
 * unit). Deducts from inventory at the application layer, not here.
 */
class ChemicalTreatment extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['service_visit_id', 'chemical_name', 'amount', 'unit'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'float'];
    }

    /** @return BelongsTo<ServiceVisit, $this> */
    public function serviceVisit(): BelongsTo
    {
        return $this->belongsTo(ServiceVisit::class);
    }
}
