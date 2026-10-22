<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One note in a work order's thread. Who wrote it is set at the call site.
 *
 * @property int $work_order_id
 * @property int|null $user_id
 * @property string $body
 * @property-read User|null $author
 */
class WorkOrderComment extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['body'];

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
