<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A repair/service entry against a piece of pool equipment. Tenant-scoped.
 *
 * @property string $description
 * @property string $cost
 * @property Carbon|null $serviced_on
 */
class EquipmentServiceLog extends Model
{
    use BelongsToTenant;

    protected $table = 'equipment_service_log';

    /** @var list<string> */
    protected $fillable = ['pool_equipment_id', 'service_visit_id', 'serviced_on', 'description', 'cost', 'created_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'serviced_on' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<PoolEquipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(PoolEquipment::class, 'pool_equipment_id');
    }
}
