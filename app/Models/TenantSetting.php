<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * TenantSetting — a per-tenant key/value (AI provider config, per-tenant
 * SMTP, landing sections, …). Secrets are encrypted by the caller before
 * storage; this is the plain k/v store.
 */
class TenantSetting extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['key', 'value'];

    /** Read a setting for a tenant (scope-free, by id). */
    public static function getFor(int $tenantId, string $key, ?string $default = null): ?string
    {
        $value = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->value('value');

        return is_string($value) ? $value : $default;
    }

    /**
     * Upsert a setting for a tenant. tenant_id is set explicitly (not via the
     * BelongsToTenant auto-fill) so this works outside a tenant context too —
     * e.g. the super-admin writing AI settings for an arbitrary tenant.
     */
    public static function setFor(int $tenantId, string $key, string $value): void
    {
        $setting = static::withoutGlobalScopes()->firstOrNew(['tenant_id' => $tenantId, 'key' => $key]);
        $setting->forceFill(['tenant_id' => $tenantId, 'value' => $value])->save();
    }
}
