<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PoolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pool — a body of water serviced for a customer. Tenant-scoped.
 * `custom_target_ranges` overrides the tenant/global chemistry targets.
 *
 * @property string $name
 * @property string $type
 * @property int|null $volume_gallons
 * @property string|null $surface_type
 * @property string $sanitizer_type
 * @property string|null $filter_type
 * @property string|null $pump_type
 * @property bool $has_heater
 * @property bool $has_automation
 * @property bool $has_pool_cleaner
 * @property bool $has_cover
 * @property bool $has_water_feature
 * @property bool $has_auto_fill
 * @property string|null $notes
 * @property array<string, array{min?: float|int, max?: float|int}>|null $custom_target_ranges
 * @property-read ServiceLocation|null $serviceLocation
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

    /** @return HasOne<ServiceLocation, $this> */
    public function serviceLocation(): HasOne
    {
        return $this->hasOne(ServiceLocation::class);
    }

    /** @return HasMany<ServiceSubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(ServiceSubscription::class);
    }

    /** @return HasMany<ServiceVisit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(ServiceVisit::class);
    }

    /** @return HasMany<PoolEquipment, $this> */
    public function equipmentItems(): HasMany
    {
        return $this->hasMany(PoolEquipment::class);
    }

    /**
     * The pool's [lat, lng] for routing, or null if it has no geocoded
     * service location. A 0/null coordinate is treated as unset.
     *
     * @return array{0: float, 1: float}|null
     */
    public function coordinates(): ?array
    {
        $loc = $this->serviceLocation;
        if ($loc === null || $loc->lat === null || $loc->lng === null || ($loc->lat === 0.0 && $loc->lng === 0.0)) {
            return null;
        }

        return [(float) $loc->lat, (float) $loc->lng];
    }
}
