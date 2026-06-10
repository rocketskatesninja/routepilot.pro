<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ScheduleOverride — a one-off deviation from the materialized cadence:
 * skip / move / add a stop for a pool on a date. `applied` flips once the
 * materializer has reconciled it into route_stops.
 */
class ScheduleOverride extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'pool_id', 'original_date', 'action', 'new_date',
        'new_agent_id', 'reason', 'created_by', 'applied',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'original_date' => 'date',
            'new_date' => 'date',
            'applied' => 'boolean',
        ];
    }

    /** @return BelongsTo<Pool, $this> */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    /** @return BelongsTo<User, $this> */
    public function newAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_agent_id');
    }
}
