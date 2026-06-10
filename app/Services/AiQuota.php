<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiUsage;
use App\Models\TenantSetting;

/**
 * Per-tenant monthly AI message allowance. The limit is a tenant setting
 * (`ai_monthly_quota`, e.g. raised by top-up packs) or the plan default.
 */
class AiQuota
{
    private const DEFAULT_LIMIT = 500;

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

        return $override !== null ? (int) $override : self::DEFAULT_LIMIT;
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
