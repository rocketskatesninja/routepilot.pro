<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pool;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;

/**
 * Platform billing meter — counts a tenant's billable usage (active pools +
 * active agents) and computes the base + per-unit overage against the plan.
 *
 * Billable pool = a non-deleted pool. Billable agent = an active user with the
 * `agent` role (the tenant_admin is not metered).
 */
class BillingMeter
{
    /** @return array<string, mixed> */
    public function for(Tenant $tenant): array
    {
        // SoftDeletes scope stays in place (trashed pools don't count); only the
        // tenant scope is lifted so we can meter an arbitrary tenant.
        $pools = Pool::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->count();

        $agents = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('role', 'agent')
            ->where('is_active', true)
            ->count();

        return $this->price($pools, $agents);
    }

    /**
     * Bulk-meter many tenants — two grouped queries instead of two per tenant.
     * Used by the platform billing overview to avoid an N+1 across every tenant.
     *
     * @param  list<int>  $tenantIds
     * @return array<int, array<string, mixed>> keyed by tenant id
     */
    public function forMany(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [];
        }

        $poolCounts = Pool::withoutGlobalScope(TenantScope::class)
            ->whereIn('tenant_id', $tenantIds)
            ->selectRaw('tenant_id, count(*) as aggregate')
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        $agentCounts = User::query()
            ->whereIn('tenant_id', $tenantIds)
            ->where('role', 'agent')
            ->where('is_active', true)
            ->selectRaw('tenant_id, count(*) as aggregate')
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        $out = [];
        foreach ($tenantIds as $id) {
            $out[$id] = $this->price((int) ($poolCounts[$id] ?? 0), (int) ($agentCounts[$id] ?? 0));
        }

        return $out;
    }

    /**
     * Pure pricing math for a given usage — base + per-pool + per-agent overage.
     *
     * @return array<string, mixed>
     */
    public function price(int $pools, int $agents): array
    {
        $base = (float) config('billing.base_price');
        $includedPools = (int) config('billing.included_pools');
        $includedAgents = (int) config('billing.included_agents');
        $perPool = (float) config('billing.price_per_pool');
        $perAgent = (float) config('billing.price_per_agent');

        $overPools = max(0, $pools - $includedPools);
        $overAgents = max(0, $agents - $includedAgents);
        $poolOverage = round($overPools * $perPool, 2);
        $agentOverage = round($overAgents * $perAgent, 2);

        return [
            'pools' => ['used' => $pools, 'included' => $includedPools, 'over' => $overPools, 'unit_price' => $perPool, 'overage' => $poolOverage],
            'agents' => ['used' => $agents, 'included' => $includedAgents, 'over' => $overAgents, 'unit_price' => $perAgent, 'overage' => $agentOverage],
            'base' => round($base, 2),
            'overage_total' => round($poolOverage + $agentOverage, 2),
            'estimated_total' => round($base + $poolOverage + $agentOverage, 2),
        ];
    }
}
