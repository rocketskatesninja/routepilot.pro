<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\ChemicalReading;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\Tenant;
use App\Models\User;

/**
 * System prompt + context for the field-tech assistant — pool/spa care
 * expertise scoped to the agent's assigned pools and today's route.
 */
class AgentContext
{
    public function build(User $agent): string
    {
        $tenantId = (int) $agent->tenant_id;

        $route = Route::query()
            ->where('agent_id', $agent->id)
            ->whereDate('scheduled_date', Tenant::localToday())
            ->with(['stops.pool.customer', 'stops.pool.serviceLocation'])
            ->first();

        $stopSummary = 'No stops scheduled today.';
        if ($route !== null && $route->stops->isNotEmpty()) {
            $completed = $route->stops->where('status', 'completed')->count();
            $total = $route->stops->count();
            $stops = $route->stops->map(function (RouteStop $s): string {
                $cust = $s->pool?->customer?->displayName() ?? '';
                $loc = $s->pool?->serviceLocation?->getAttribute('address_line1');

                return "#{$s->stop_order} {$cust} — {$s->pool?->name} ({$s->status})".($loc ? " at {$loc}" : '');
            })->join("\n  ");
            $stopSummary = "Today's route: {$completed}/{$total} completed\n  {$stops}";
        }

        $assignedPoolIds = ServiceSubscription::query()
            ->where('assigned_agent_id', $agent->id)
            ->where('status', 'active')
            ->pluck('pool_id');

        $recentReadings = ChemicalReading::query()
            ->whereHas('serviceVisit', fn ($q) => $q->whereIn('pool_id', $assignedPoolIds))
            ->with('serviceVisit.pool')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ChemicalReading $r): string => "{$r->serviceVisit->pool->name}: FC={$r->free_chlorine} pH={$r->ph} TA={$r->alkalinity} CH={$r->calcium_hardness} CYA={$r->cyanuric_acid} LSI={$r->lsi_score}")
            ->join("\n  ");

        $context = "AGENT: {$agent->displayName()}\n"
            .'Date: '.Tenant::localToday()->format('l, F j, Y')."\n"
            ."{$stopSummary}\n"
            .($recentReadings !== '' ? "\nRecent chemistry readings:\n  {$recentReadings}\n" : '');

        return "You are RoutePilot AI, an expert pool & spa care assistant for technician {$agent->displayName()}. "
            .'You help with water chemistry (LSI, dosing, treatment), equipment troubleshooting (pumps, filters, '
            .'salt cells, heaters), service best practices, and problem diagnosis (green/cloudy water, staining, '
            ."scaling). Be specific and practical; reference the pool's actual data when available.\n\n"
            .$context;
    }
}
