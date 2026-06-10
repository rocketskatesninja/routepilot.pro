<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PoolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pool — a body of water serviced for a customer. Tenant-scoped.
 * `custom_target_ranges` overrides the tenant/global chemistry targets.
 */
class Pool extends Model
{
    /** @use HasFactory<PoolFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'customer_id', 'name', 'type', 'volume_gallons', 'surface_type',
        'sanitizer_type', 'filter_type', 'pump_type', 'has_heater',
        'has_automation', 'has_pool_cleaner', 'has_cover', 'has_water_feature',
        'has_auto_fill', 'features', 'custom_target_ranges', 'notes', 'requested_tasks',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'custom_target_ranges' => 'array',
            'requested_tasks' => 'array',
            'has_heater' => 'boolean',
            'has_automation' => 'boolean',
            'has_pool_cleaner' => 'boolean',
            'has_cover' => 'boolean',
            'has_water_feature' => 'boolean',
            'has_auto_fill' => 'boolean',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
