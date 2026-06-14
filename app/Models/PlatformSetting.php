<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PlatformSetting — a global (tenant-less) key/value store for super-admin
 * platform configuration: the AI default provider/model, the platform AI keys
 * (encrypted by the caller before storage), and the default monthly quota.
 *
 * Mirrors TenantSetting but without tenancy. Secrets are encrypted by the
 * caller (see PlatformAiSettings), never in plaintext here.
 */
class PlatformSetting extends Model
{
    /** @var list<string> */
    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = static::query()->where('key', $key)->value('value');

        return is_string($value) ? $value : $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
