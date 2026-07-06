<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\Customer;
use App\Models\Pool;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * System prompt + live business data for the tenant-admin assistant. The
 * data block is a SUMMARY — details (addresses, readings, balances) come
 * from the lookup tools.
 */
class TenantContext
{
    public function build(int $tenantId): string
    {
        $name = Tenant::query()->whereKey($tenantId)->value('name');
        $tenantName = is_string($name) ? $name : 'your company';

        $agents = User::query()->where('tenant_id', $tenantId)->where('role', 'agent')->where('is_active', true)->get();
        $agentList = $agents->map(function (User $a): string {
            $pools = ServiceSubscription::query()->where('assigned_agent_id', $a->id)->where('status', 'active')->count();

            return "- {$a->displayName()} [id:{$a->id}] ({$pools} pools)";
        })->join("\n") ?: 'No agents.';

        $customers = Customer::query()->with([
            'pools.subscriptions' => fn ($q) => $q->where('status', 'active'),
            'pools.subscriptions.serviceType',
            'pools.subscriptions.agent',
        ])->get();

        $customerList = $customers->map(function (Customer $c): string {
            $pools = $c->pools->map(function (Pool $p): string {
                $subs = $p->subscriptions->map(function (ServiceSubscription $s): string {
                    $agent = $s->agent?->displayName() ?? 'unassigned';

                    return "{$s->serviceType->name} ({$s->scheduleLabel()}) — agent: {$agent}";
                })->join('; ');
                $vol = $p->volume_gallons !== null ? number_format($p->volume_gallons).' gal' : '?';

                return "  Pool: {$p->name} [id:{$p->id}] ({$p->type}, {$vol})".($subs !== '' ? " → {$subs}" : ' (no active subscription)');
            })->join("\n");

            return "- {$c->displayName()} [id:{$c->id}]".($c->email !== null ? " ({$c->email})" : '')."\n{$pools}";
        })->join("\n") ?: 'No customers.';

        $todayStops = RouteStop::query()
            ->whereHas('route', fn ($q) => $q->where('tenant_id', $tenantId)->whereDate('scheduled_date', Tenant::localToday()))
            ->with('route.agent', 'pool.customer')
            ->orderBy('stop_order')
            ->get();
        $todaySummary = $todayStops->isEmpty()
            ? 'No stops scheduled today.'
            : $todayStops->map(fn (RouteStop $s): string => '- '.($s->pool?->customer?->displayName() ?? '?')." / {$s->pool?->name} → ".($s->route?->agent?->displayName() ?? '?')." ({$s->status})")->join("\n");

        $unassigned = ServiceSubscription::query()->where('status', 'active')->whereNull('assigned_agent_id')->count();
        $overdue = ServiceVisit::query()->where('status', 'completed')->whereNull('paid_at')->where('visited_at', '<', Carbon::now()->subDays(30))->count();
        $warnings = '';
        if ($unassigned > 0) {
            $warnings .= "⚠ {$unassigned} active subscriptions have no assigned agent\n";
        }
        if ($overdue > 0) {
            $warnings .= "⚠ {$overdue} completed visits are unpaid (30+ days)\n";
        }

        $data = "BUSINESS DATA for {$tenantName} (as of ".Tenant::localToday()->format('l, F j, Y')."):\n\n"
            ."AGENTS:\n{$agentList}\n\n"
            ."CUSTOMERS & POOLS:\n{$customerList}\n\n"
            ."TODAY'S SCHEDULE:\n{$todaySummary}\n\n"
            .($warnings !== '' ? "WARNINGS:\n{$warnings}\n" : '');

        return "You are RoutePilot AI, an expert pool-service business assistant for \"{$tenantName}\". "
            .'Use the real business data below to answer accurately. You help with water chemistry, '
            ."scheduling and routing, agent and customer management, equipment, and inventory.\n\n"
            .'The data below is a SUMMARY — it does NOT include addresses, chemistry readings, visit history, '
            .'balances, or detailed specs. When asked for details not shown, you MUST call a lookup tool rather '
            ."than saying you lack the data. When asked to make a change that matches an action tool, call it.\n\n"
            .'Back-office surfaces: Dashboard (/dashboard), Pools (/pools), People (/people), Services (/services), '
            ."Assistant (/assistant). Match customer names loosely; if multiple pools match and the admin didn't "
            ."specify, ask which.\n\n"
            ."Your available tools:\n".ToolRegistry::descriptionList()."\n\n"
            .'ENTITY LINKING: when you mention a specific customer, pool, agent, or inventory item by name AND you '
            .'know its id from the data, wrap it as [[type:id:Display Name]] (types: customer, pool, agent, inventory). '
            ."Example: [[customer:5:John Smith]]. Never invent ids.\n\n"
            .$data;
    }
}
