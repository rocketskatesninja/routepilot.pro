<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ServiceVisitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * ServiceVisit — the record of work done at a pool: checklist tasks,
 * the chemical reading, treatments applied, and photos. A null
 * route_stop_id means an ad-hoc (off-route) visit.
 *
 * @property string $status
 * @property string|null $notes
 * @property Carbon|null $visited_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $paid_at
 */
class ServiceVisit extends Model
{
    /** @use HasFactory<ServiceVisitFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'route_stop_id', 'pool_id', 'agent_id', 'visited_at',
        'completed_at', 'paid_at', 'status', 'signature_path', 'notes',
        'completed_lat', 'completed_lng',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
            'completed_at' => 'datetime',
            'paid_at' => 'datetime',
            'completed_lat' => 'float',
            'completed_lng' => 'float',
        ];
    }

    /** @return BelongsTo<RouteStop, $this> */
    public function routeStop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class);
    }

    /** @return BelongsTo<Pool, $this> */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /** @return HasOne<ChemicalReading, $this> */
    public function chemicalReading(): HasOne
    {
        return $this->hasOne(ChemicalReading::class);
    }

    /** @return HasMany<ChemicalTreatment, $this> */
    public function treatments(): HasMany
    {
        return $this->hasMany(ChemicalTreatment::class);
    }

    /** @return HasMany<ServiceTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(ServiceTask::class);
    }

    /** @return HasMany<VisitPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(VisitPhoto::class);
    }

    /**
     * Chemistry trend (chlorine, pH, dates) for the portal + reports sparklines.
     * Expects a chronological (oldest → newest) collection of completed visits
     * with chemicalReading loaded.
     *
     * @param  Collection<int, ServiceVisit>  $visits
     * @return array{chlorine: list<float>, ph: list<float>, dates: list<string>}
     */
    public static function chemistryTrend(Collection $visits): array
    {
        $withReading = $visits->filter(fn (self $v): bool => $v->chemicalReading !== null)->values();

        return [
            'chlorine' => $withReading->map(fn (self $v): ?float => $v->chemicalReading?->free_chlorine)->filter(fn (?float $x): bool => $x !== null)->values()->all(),
            'ph' => $withReading->map(fn (self $v): ?float => $v->chemicalReading?->ph)->filter(fn (?float $x): bool => $x !== null)->values()->all(),
            'dates' => $withReading->map(fn (self $v): ?string => $v->completed_at?->format('M j'))->filter()->values()->all(),
        ];
    }
}
