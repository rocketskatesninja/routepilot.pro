<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something a customer asks their service company for, from the portal (or a
 * converted website lead). The company answers it once: approving a service
 * request creates its plan; a pause or stop applies to the one plan it names;
 * or it declines with a reason. Status and the plan link change only at
 * controlled call sites (not mass-assignable).
 *
 * @property string $type
 * @property string|null $message
 * @property string $status
 * @property string|null $reply
 * @property Carbon|null $preferred_date
 * @property Carbon|null $ends_on
 * @property Carbon|null $resolved_at
 * @property-read ServiceType|null $serviceType older free-text requests have none
 * @property-read ServiceSubscription|null $plan
 */
class ServiceRequest extends Model
{
    use BelongsToTenant;

    /** What a customer can ask for, as they see it. */
    public const TYPES = [
        'service' => 'Request a service',
        'hold' => 'Pause service',
        'cancel' => 'Stop service',
        'other' => 'Note',
    ];

    /** @var list<string> */
    protected $fillable = ['customer_id', 'pool_id', 'service_type_id', 'type', 'message', 'preferred_date', 'ends_on'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'ends_on' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    /**
     * The plan the request is about: the one approving a service request
     * created, or the one a pause / stop is for.
     *
     * @return BelongsTo<ServiceSubscription, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(ServiceSubscription::class, 'service_subscription_id');
    }

    /** A request for a catalog service (older free-text requests have none). */
    public function isForService(): bool
    {
        return $this->type === 'service' && $this->getAttribute('service_type_id') !== null;
    }

    /** What the request is for, e.g. "Weekly Pool Service" or "Pause Chemistry Check". */
    public function title(): string
    {
        return match ($this->type) {
            'service' => $this->serviceType->name ?? 'Service request',
            'hold' => 'Pause '.($this->plan->serviceType->name ?? 'service'),
            'cancel' => 'Stop '.($this->plan->serviceType->name ?? 'service'),
            default => self::TYPES[$this->type] ?? ucfirst($this->type),
        };
    }

    /** The outcome in plain words, e.g. "Scheduled" or "Declined". */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'declined' => 'Declined',
            default => match (true) {
                $this->isForService() => 'Scheduled',
                $this->type === 'hold' => 'Pause set',
                $this->type === 'cancel' => 'Stop confirmed',
                default => 'Done',
            },
        };
    }
}
