<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\InventoryTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * InventoryTransaction — a stock movement: usage (treatment deduction),
 * restock, or manual adjustment. The audit trail behind ChemicalInventory's
 * running balance.
 */
class InventoryTransaction extends Model
{
    /** @use HasFactory<InventoryTransactionFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'chemical_inventory_id', 'type', 'quantity', 'service_visit_id',
        'agent_id', 'pool_id', 'notes', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    /** @return BelongsTo<ChemicalInventory, $this> */
    public function inventory(): BelongsTo
    {
        return $this->belongsTo(ChemicalInventory::class, 'chemical_inventory_id');
    }

    /** @return BelongsTo<ServiceVisit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(ServiceVisit::class, 'service_visit_id');
    }

    /** @return BelongsTo<Pool, $this> */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
