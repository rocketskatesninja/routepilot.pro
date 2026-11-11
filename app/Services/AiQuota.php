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

    /**
     * What's left for this kind of account. Customers can't use the last share
     * of the month (config ai.staff_reserve), so the office and techs always
     * have some left however much customers ask.
     */
    public function remainingFor(int $tenantId, string $role): int
    {
        $reserve = $role === 'customer' ? (int) ceil($this->limit($tenantId) * (float) config('ai.staff_reserve', 0)) : 0;

        return max(0, $this->remaining($tenantId) - $reserve);
    }

    /** Count one AI message against the tenant's monthly allowance. */
    public function record(int $tenantId): void
    {
        $usage = AiUsage::query()->firstOrCreate(['tenant_id' => $tenantId, 'period' => now()->format('Y-m')]);
        $usage->increment('messages');
    }

    /** Add what a message cost (tokens and USD) to this month's record; the allowance itself counts messages. */
    public function addTokens(int $tenantId, int $tokens, float $cost): void
    {
        if ($tokens <= 0) {
            return;
        }
        $usage = AiUsage::query()->firstOrCreate(['tenant_id' => $tenantId, 'period' => now()->format('Y-m')]);
        $usage->forceFill(['tokens' => (int) $usage->getAttribute('tokens') + $tokens, 'cost' => round((float) $usage->getAttribute('cost') + $cost, 4)])->save();
    }
}
