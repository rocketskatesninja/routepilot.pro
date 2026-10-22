<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Something the company needs to get done: raised by a tech from a visit, or
 * added by the office. Office-only; customers never see work orders. Status
 * and who closed it change only at controlled call sites (not mass-assignable).
 *
 * @property int $tenant_id
 * @property string $title
 * @property string|null $details
 * @property string $status open | done
 * @property bool $urgent
 * @property Carbon|null $due_on
 * @property string|null $price what to charge for it (decimal string)
 * @property int|null $assigned_to
 * @property int|null $pool_id
 * @property int|null $service_visit_id
 * @property int|null $created_by
 * @property int|null $completed_by
 * @property Carbon|null $completed_at
 * @property-read Pool|null $pool
 * @property-read ServiceVisit|null $visit
 * @property-read User|null $assignee
 * @property-read User|null $creator
 * @property-read User|null $completer
 */
class WorkOrder extends Model
{
    /** @use HasFactory<WorkOrderFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $fillable = ['title', 'details', 'urgent', 'due_on', 'price', 'assigned_to', 'pool_id', 'service_visit_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'urgent' => 'boolean',
            'due_on' => 'date',
            'price' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    /** @param Builder<WorkOrder> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', 'open');
    }

    /**
     * Most pressing first: urgent, then by due date (overdue and soonest first, undated
     * last), then newest.
     *
     * @param  Builder<WorkOrder>  $query
     */
    public function scopePressingFirst(Builder $query): void
    {
        $query->orderByDesc('urgent')->orderByRaw('due_on is null')->orderBy('due_on')->latest('id');
    }

    /**
     * About one customer: on one of their pools, or raised from a visit to one.
     *
     * @param  Builder<WorkOrder>  $query
     */
    public function scopeAboutCustomer(Builder $query, int $customerId): void
    {
        $pools = Pool::withTrashed()->where('customer_id', $customerId)->select('id');
        $query->where(fn (Builder $q) => $q->whereIn('pool_id', $pools)
            ->orWhereIn('service_visit_id', ServiceVisit::query()->whereIn('pool_id', $pools)->select('id')));
    }

    /** @return BelongsTo<Pool, $this> */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class)->withTrashed();
    }

    /** @return BelongsTo<ServiceVisit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(ServiceVisit::class, 'service_visit_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by')->withTrashed();
    }

    /**
     * The notes thread, oldest first.
     *
     * @return HasMany<WorkOrderComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(WorkOrderComment::class)->oldest('id');
    }

    /** @return HasMany<Charge, $this> */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    /** The charge that billed it, unless that was cancelled. */
    public function billedCharge(): ?Charge
    {
        return $this->charges()->active()->latest('id')->first();
    }

    /** Whose balance it would go on: its pool's customer (not archived), while it's not billed yet. */
    public function customerToBill(): ?Customer
    {
        $customer = $this->pool?->customer;

        return $customer !== null && $this->billedCharge() === null ? $customer : null;
    }

    /** Still open and past its due date, on the company's own calendar. */
    public function isOverdue(): bool
    {
        return $this->status === 'open' && $this->due_on !== null && $this->due_on->toDateString() < Tenant::localToday()->toDateString();
    }
}
