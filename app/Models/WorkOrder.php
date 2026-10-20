<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property int|null $assigned_to
 * @property int|null $pool_id
 * @property int|null $service_visit_id
 * @property int|null $created_by
 * @property Carbon|null $completed_at
 * @property-read Pool|null $pool
 * @property-read ServiceVisit|null $visit
 * @property-read User|null $assignee
 * @property-read User|null $creator
 */
class WorkOrder extends Model
{
    /** @use HasFactory<WorkOrderFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $fillable = ['title', 'details', 'urgent', 'due_on', 'assigned_to', 'pool_id', 'service_visit_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'urgent' => 'boolean',
            'due_on' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /** @param Builder<WorkOrder> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', 'open');
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
}
