<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\ServiceSubscription;
use App\Models\User;
use App\Services\Chat\AiTool;

/**
 * Reassign a customer's pool to a different agent (permanent — all future
 * visits).
 */
class ReassignAgent extends AiTool
{
    public function name(): string
    {
        return 'reassign_agent';
    }

    public function description(): string
    {
        return 'Reassign a customer\'s pool to a different agent. '
            .'Use this when the tenant asks to change which technician services a pool.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'customer_name' => ['type' => 'string', 'description' => 'The customer\'s name'],
                'pool_name' => ['type' => 'string', 'description' => 'The pool name (optional if customer has one pool)'],
                'agent_name' => ['type' => 'string', 'description' => 'The new agent\'s name'],
            ],
            'required' => ['customer_name', 'agent_name'],
        ];
    }

    public function execute(array $params, int $tenantId): string
    {
        $customerName = trim((string) ($params['customer_name'] ?? ''));
        $poolName = trim((string) ($params['pool_name'] ?? ''));
        $agentName = trim((string) ($params['agent_name'] ?? ''));

        $agent = User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(fn ($q) => $this->whereNameLike($q, $agentName))
            ->first();
        if ($agent === null) {
            return "No active agent found matching \"{$agentName}\".";
        }

        $subs = ServiceSubscription::query()
            ->where('status', 'active')
            ->whereHas('pool.customer', fn ($q) => $this->whereNameLike($q, $customerName))
            ->when($poolName !== '', fn ($q) => $q->whereHas('pool', fn ($p) => $p->where('name', 'like', "%{$poolName}%")))
            ->with('pool.customer', 'agent')
            ->get();

        if ($subs->isEmpty()) {
            return "No active subscription found for \"{$customerName}\".";
        }
        if ($subs->count() > 1 && $poolName === '') {
            $list = $subs->map(fn (ServiceSubscription $s): string => "- {$s->pool?->name} (currently: ".($s->agent?->displayName() ?? 'unassigned').')')->join("\n");

            return "Multiple subscriptions found. Please specify the pool:\n{$list}";
        }

        $sub = $subs->first();
        $oldAgent = $sub->agent?->displayName() ?? 'unassigned';
        $sub->update(['assigned_agent_id' => $agent->id]);

        return "Done! Reassigned {$sub->pool?->customer?->displayName()}'s \"{$sub->pool?->name}\" from {$oldAgent} to {$agent->displayName()}.";
    }
}
