<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AuditLog — an append-only record of a sensitive action (billing,
 * permissions, deletes, impersonation, exports). tenant_id is null for
 * platform-level (super-admin) actions.
 *
 * @property array<string, mixed>|null $changes
 */
class AuditLog extends Model
{
    use BelongsToTenant;

    protected $table = 'audit_log';

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'action', 'model_type', 'model_id', 'changes', 'ip_address',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
