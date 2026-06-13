<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The public landing's live-data cache (stats / gallery / team), keyed per
 * tenant. Busted whenever its inputs change: the landing config is saved, or a
 * photo's showcase flag is toggled.
 */
class LandingCache
{
    public static function key(int $tenantId): string
    {
        return "landing:live:{$tenantId}";
    }

    public static function forget(int $tenantId): void
    {
        Cache::forget(self::key($tenantId));
    }
}
