<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ServiceLocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ServiceLocation — where the pool physically is: geocoded address plus
 * the access details (gate code, notes) the field app surfaces early.
 * Scoped through its Pool (no tenant_id of its own).
 *
 * @property float|null $lat
 * @property float|null $lng
 */
class ServiceLocation extends Model
{
    /** @use HasFactory<ServiceLocationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'pool_id', 'address_line1', 'address_line2', 'city', 'state', 'zip',
        'lat', 'lng', 'access_notes', 'gate_code', 'photo_path',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float'];
    }

    /** @return BelongsTo<Pool, $this> */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }
}
