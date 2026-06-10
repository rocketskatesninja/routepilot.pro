<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RouteStopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * RouteStop — one appointment on a route. Tenant scope is inherited
 * through the parent Route (no tenant_id column of its own). A null
 * service_subscription_id marks an impromptu (one-off) visit.
 */
class RouteStop extends Model
{
    /** @use HasFactory<RouteStopFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'route_id', 'pool_id', 'service_subscription_id', 'stop_order',
        'status', 'estimated_arrival', 'actual_arrival', 'completed_at', 'skip_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estimated_arrival' => 'datetime',
            'actual_arrival' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Route, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    /** @return BelongsTo<Pool, $this> */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    /** @return BelongsTo<ServiceSubscription, $this> */
    public function serviceSubscription(): BelongsTo
    {
        return $this->belongsTo(ServiceSubscription::class);
    }

    /** @return HasOne<ServiceVisit, $this> */
    public function serviceVisit(): HasOne
    {
        return $this->hasOne(ServiceVisit::class);
    }
}
