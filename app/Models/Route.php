<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\RouteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Route — one agent's day: the shared record both the back office and
 * the agent read/write, holding ordered RouteStops for a scheduled date.
 *
 * @property Carbon $scheduled_date
 */
class Route extends Model
{
    /** @use HasFactory<RouteFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $fillable = ['agent_id', 'scheduled_date', 'start_time', 'status', 'optimized_order', 'notes'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'optimized_order' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /** @return HasMany<RouteStop, $this> */
    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class)->orderBy('stop_order');
    }
}
