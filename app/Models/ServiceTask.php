<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ServiceTask — one checklist item on a visit, instantiated from the
 * service type's task template when the agent arrives.
 */
class ServiceTask extends Model
{
    /** @var list<string> */
    protected $fillable = ['service_visit_id', 'task_name', 'is_completed'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_completed' => 'boolean'];
    }

    /** @return BelongsTo<ServiceVisit, $this> */
    public function serviceVisit(): BelongsTo
    {
        return $this->belongsTo(ServiceVisit::class);
    }
}
