<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ChemicalInventoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ChemicalInventory — central tenant stock of a chemical. Treatments deduct
 * from it; stock at/below `reorder_threshold` drives reorder alerts. Modeled
 * with a future per-truck location in mind (no truck_id yet).
 *
 * @property string $chemical_name
 * @property string $unit
 * @property string|null $supplier
 * @property bool $is_active
 */
class ChemicalInventory extends Model
{
    /** @use HasFactory<ChemicalInventoryFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'chemical_inventory';

    /** @var list<string> */
    protected $fillable = [
        'chemical_name', 'unit', 'current_stock', 'reorder_threshold',
        'cost_per_unit', 'sell_price', 'supplier', 'notes', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'current_stock' => 'decimal:2',
            'reorder_threshold' => 'decimal:2',
            'cost_per_unit' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<InventoryTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    /** Stock has fallen to or below the reorder threshold. */
    public function isLowStock(): bool
    {
        return $this->reorder_threshold !== null && (float) $this->current_stock <= (float) $this->reorder_threshold;
    }
}
