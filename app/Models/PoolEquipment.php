<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A piece of equipment on a pool (pump/filter/heater/salt cell …). Tenant-scoped.
 *
 * @property string $type
 * @property Carbon|null $installed_on
 * @property Carbon|null $warranty_until
 */
class PoolEquipment extends Model
{
    use BelongsToTenant;

    protected $table = 'pool_equipment';

    /** @var list<string> */
    protected $fillable = ['pool_id', 'type', 'make', 'model', 'serial', 'installed_on', 'warranty_until', 'notes'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'installed_on' => 'date',
            'warranty_until' => 'date',
        ];
    }

    /** @return BelongsTo<Pool, $this> */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    /** @return HasMany<EquipmentServiceLog, $this> */
    public function serviceLog(): HasMany
    {
        return $this->hasMany(EquipmentServiceLog::class);
    }
}
