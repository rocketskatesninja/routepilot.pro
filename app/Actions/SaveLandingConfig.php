<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Support\LandingCache;
use App\Support\LandingConfig;

/**
 * Persist a tenant's landing config: run the submitted document through the
 * LandingConfig::sanitize() trust boundary (whitelist keys + fields, cap
 * arrays, clean image paths), store it, and bust the live-data cache.
 */
class SaveLandingConfig
{
    /** @param  array<string, mixed>  $input */
    public function handle(Tenant $tenant, array $input): void
    {
        $clean = LandingConfig::sanitize($input);
        TenantSetting::setFor($tenant->id, 'landing', (string) json_encode($clean));
        LandingCache::forget((int) $tenant->id);
    }
}
