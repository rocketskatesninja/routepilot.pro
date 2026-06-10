<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Database\Factories\ServiceSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * ServiceSubscription — a pool's recurring service plan: which service
 * type, at what cadence, worked by which agent. The materializer turns
 * these into concrete RouteStops. `frequency_details` carries the
 * cadence specifics (week_type for biweekly, day_of_month for monthly).
 * A dated hold window suspends materialization and auto-resumes.
 *
 * @property array<string, mixed>|null $frequency_details
 * @property Carbon|null $hold_starts_at
 * @property Carbon|null $hold_ends_at
 */
class ServiceSubscription extends Model
{
    /** @use HasFactory<ServiceSubscriptionFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'pool_id', 'service_type_id', 'assigned_agent_id', 'frequency',
        'frequency_details', 'preferred_day', 'secondary_preferred_day',
        'preferred_time_start', 'preferred_time_end',
        'hold_starts_at', 'hold_ends_at', 'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'frequency_details' => 'array',
            'hold_starts_at' => 'date',
            'hold_ends_at' => 'date',
        ];
    }

    /** @return BelongsTo<Pool, $this> */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    /** @return BelongsTo<ServiceType, $this> */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    /** A date falls inside this subscription's vacation-hold window. */
    public function isOnHold(CarbonInterface $date): bool
    {
        return $this->hold_starts_at !== null
            && $this->hold_ends_at !== null
            && $date->betweenIncluded($this->hold_starts_at, $this->hold_ends_at);
    }
}
