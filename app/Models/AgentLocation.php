<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * AgentLocation — an agent's last-known GPS position (one row per agent,
 * upserted each ping). Tenant-scoped; purged after a short retention window.
 *
 * @property Carbon|null $recorded_at
 */
class AgentLocation extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['agent_id', 'lat', 'lng', 'heading', 'accuracy', 'recorded_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
