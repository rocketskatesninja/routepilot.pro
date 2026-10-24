<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ChemicalInventoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * ChemicalInventory — central tenant stock of a chemical. Treatments deduct
 * from it; stock at/below `reorder_threshold` shows it as running low. Every
 * change goes through move(), so its transactions always add up to the stock.
 * Archiving (is_active false) takes it off the list and out of visit
 * deductions; its history stays.
 *
 * @property string $chemical_name
 * @property string|null $standard_key which standard chemical this is (App\Support\StandardChemicals), if any
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

    /**
     * Chemicals in use (not archived).
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return HasMany<InventoryTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    /**
     * Change the stock by $change (it never goes below zero) and log what actually
     * moved. Logging the real change, not the requested one, is what lets a visit's
     * usage be put back exactly when its report is edited or deleted.
     *
     * @param  'restock'|'usage'|'adjustment'  $type
     * @param  array<string, mixed>  $context  service_visit_id, agent_id, pool_id, created_by
     */
    public function move(string $type, float $change, array $context = []): InventoryTransaction
    {
        return DB::transaction(function () use ($type, $change, $context): InventoryTransaction {
            // Read the stock under a row lock so two visits syncing at once can't overwrite each other.
            $before = (float) static::query()->whereKey($this->id)->lockForUpdate()->value('current_stock');
            $after = max(0.0, round($before + $change, 2));
            $this->update(['current_stock' => $after]);

            return $this->transactions()->create([...$context, 'type' => $type, 'quantity' => round($after - $before, 2)]);
        });
    }

    /**
     * Once a chemical has stock or history its unit can't change: switching from lbs
     * to bags would turn "25 lbs" into "25 bags". A new unit means a new chemical.
     */
    public function unitLocked(): bool
    {
        return (float) $this->current_stock > 0 || $this->transactions()->exists();
    }

    /** Stock has fallen to or below the reorder threshold. */
    public function isLowStock(): bool
    {
        return $this->reorder_threshold !== null && (float) $this->current_stock <= (float) $this->reorder_threshold;
    }
}
