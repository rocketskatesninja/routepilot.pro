<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Integration — per-tenant third-party config (AI keys, SMTP, etc.).
 *
 * Audit fix: `config` is cast to `encrypted:array` so secrets (API keys)
 * are encrypted at rest, never stored as plaintext JSON.
 */
class Integration extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['provider', 'integration_type', 'config', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }
}
