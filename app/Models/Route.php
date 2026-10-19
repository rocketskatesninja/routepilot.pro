<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\RouteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
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
    protected $fillable = ['agent_id', 'scheduled_date', 'status', 'notes'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
        ];
    }

    /**
     * A tech's route for a day (with no tech, the day's "unassigned" route),
     * created on first use. Explicitly tenant-filtered, so it behaves the same
     * from the nightly job (no tenant bound) as from a request. A tech's route is
     * unique per day in the database; if a concurrent request created it first,
     * that one is returned.
     */
    public static function forDay(int $tenantId, ?int $agentId, string $date): self
    {
        $route = self::findForDay($tenantId, $agentId, $date);
        if ($route !== null) {
            return $route;
        }

        // tenant_id is deliberately not fillable (charter: privilege fields via forceFill).
        $route = new self(['agent_id' => $agentId, 'scheduled_date' => $date, 'status' => 'scheduled']);
        try {
            $route->forceFill(['tenant_id' => $tenantId])->save();
        } catch (UniqueConstraintViolationException $e) {
            return self::findForDay($tenantId, $agentId, $date) ?? throw $e;
        }

        return $route;
    }

    private static function findForDay(int $tenantId, ?int $agentId, string $date): ?self
    {
        return self::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->when($agentId !== null, fn ($q) => $q->where('agent_id', $agentId), fn ($q) => $q->whereNull('agent_id'))
            ->whereDate('scheduled_date', $date)
            ->first();
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
