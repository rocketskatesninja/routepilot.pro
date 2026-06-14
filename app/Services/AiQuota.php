<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiUsage;
use App\Models\TenantSetting;

/**
 * Per-tenant monthly AI message allowance. The limit is a per-tenant override
 * (`ai_monthly_quota`, set by the super-admin) or the platform default quota.
 */
class AiQuota
{
    public function __construct(private PlatformAiSettings $platform) {}

    /** Whether AI is enabled for the tenant (super-admin per-tenant toggle, default on). */
    public function enabled(int $tenantId): bool
    {
        return TenantSetting::getFor($tenantId, 'ai_enabled', '1') !== '0';
    }

    public function used(int $tenantId): int
    {
        return (int) (AiUsage::query()
            ->where('tenant_id', $tenantId)
            ->where('period', now()->format('Y-m'))
            ->value('messages') ?? 0);
    }

    public function limit(int $tenantId): int
    {
        $override = TenantSetting::getFor($tenantId, 'ai_monthly_quota');

        return $override !== null && $override !== '' ? max(0, (int) $override) : $this->platform->defaultQuota();
    }

    public function remaining(int $tenantId): int
    {
        return max(0, $this->limit($tenantId) - $this->used($tenantId));
    }

    /** Count one AI message against the tenant's monthly allowance. */
    public function record(int $tenantId): void
    {
        $usage = AiUsage::query()->firstOrCreate(['period' => now()->format('Y-m')]);
        $usage->increment('messages');
    }
}
