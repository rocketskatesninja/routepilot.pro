<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SystemSetting — a global platform key/value (not tenant-scoped).
 */
class SystemSetting extends Model
{
    /** @var list<string> */
    protected $fillable = ['key', 'value'];

    public static function getFor(string $key, ?string $default = null): ?string
    {
        $value = static::query()->where('key', $key)->value('value');

        return is_string($value) ? $value : $default;
    }

    public static function setFor(string $key, string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
