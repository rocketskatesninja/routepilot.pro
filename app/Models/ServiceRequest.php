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
 * request creates its plan, a pause sets a hold, a stop ends the pool's plans;
 * or it declines with a reason. Status changes only via the Approve/Decline
 * actions (not mass-assignable).
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
        'other' => 'Something else',
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
     * The plan that approving this request created.
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

    /** What the request is for, e.g. "Weekly Pool Service" or "Pause service". */
    public function title(): string
    {
        if ($this->type === 'service') {
            return $this->serviceType->name ?? 'Service request';
        }

        return self::TYPES[$this->type] ?? ucfirst($this->type);
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
                $this->type === 'cancel' => 'Service stopped',
                default => 'Done',
            },
        };
    }
}
