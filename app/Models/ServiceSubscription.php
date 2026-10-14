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
 * ServiceSubscription — a pool's service plan: which service, on what schedule,
 * worked by which tech. The SubscriptionMaterializer turns plans into dated
 * RouteStops; every change to a plan goes through the Create/Update/Delete
 * subscription actions so its upcoming stops always match it.
 *
 * Schedules: weekly / every 2 weeks (counted from starts_on) on preferred_day,
 * monthly on day_of_month, or one_time (a single visit on starts_on). An
 * optional ends_on finishes the plan (seasonal service); a dated hold window
 * pauses it and resumes automatically.
 *
 * @property string $frequency
 * @property string|null $preferred_day
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property int|null $day_of_month
 * @property Carbon|null $hold_starts_at
 * @property Carbon|null $hold_ends_at
 * @property string $status
 */
class ServiceSubscription extends Model
{
    /** @use HasFactory<ServiceSubscriptionFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'pool_id', 'service_type_id', 'assigned_agent_id', 'frequency', 'preferred_day',
        'starts_on', 'ends_on', 'day_of_month', 'hold_starts_at', 'hold_ends_at', 'status',
    ];

    /** Schedule types, with the label people see. */
    public const FREQUENCIES = [
        'weekly' => 'Weekly',
        'biweekly' => 'Every 2 weeks',
        'monthly' => 'Monthly',
        'one_time' => 'One-time',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'day_of_month' => 'integer',
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

    /** Human-readable schedule, e.g. "Weekly on Tuesday" or "One-time on Jun 17". */
    public function scheduleLabel(): string
    {
        $day = $this->preferred_day !== null && $this->preferred_day !== '' ? ' on '.ucfirst($this->preferred_day) : '';
        $label = match ($this->frequency) {
            'weekly' => 'Weekly'.$day,
            'biweekly' => 'Every 2 weeks'.$day,
            'monthly' => 'Monthly'.($this->day_of_month !== null ? ' on day '.$this->day_of_month : ''),
            'one_time' => 'One-time'.($this->starts_on !== null ? ' on '.$this->starts_on->format('M j') : ''),
            default => ucfirst(str_replace('_', ' ', $this->frequency)),
        };

        return $this->ends_on !== null && $this->frequency !== 'one_time'
            ? $label.', until '.$this->ends_on->format('M j')
            : $label;
    }
}
