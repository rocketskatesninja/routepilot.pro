<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\Customer;
use App\Models\Pool;
use App\Models\RouteStop;
use App\Models\ServiceVisit;
use App\Models\Tenant;

/**
 * The customer assistant's live data: this customer's own pools, plans, recent
 * visits and next service day (never a time), as their portal shows them.
 */
class CustomerContext
{
    public function build(Customer $customer): string
    {
        $pools = $customer->pools()->with('subscriptions.serviceType', 'subscriptions.agent')->get();
        $poolIds = $pools->pluck('id');

        $poolSummary = $pools->map(function (Pool $pool): string {
            $subs = $pool->subscriptions->where('status', 'active')->map(
                fn ($s): string => $s->serviceType->name.' — '.$s->scheduleLabel().' with '.($s->agent?->displayName() ?? 'your technician')
            )->join('; ');
            $vol = $pool->volume_gallons !== null ? $pool->volume_gallons.' gal' : 'unknown size';

            return "- {$pool->name} ({$pool->type}, {$vol}, {$pool->sanitizer_type})".($subs !== '' ? "\n  Service: {$subs}" : '');
        })->join("\n");

        $recentVisits = ServiceVisit::query()
            ->whereIn('pool_id', $poolIds)
            ->where('status', 'completed')
            ->with('pool', 'agent', 'chemicalReading')
            ->latest('completed_at')
            ->limit(5)
            ->get()
            ->map(function (ServiceVisit $v): string {
                $r = $v->chemicalReading;
                $chem = $r !== null ? "FC={$r->free_chlorine} pH={$r->ph}".($r->lsi_score !== null ? " LSI={$r->lsi_score}" : '') : 'no readings';

                return '- '.($v->completed_at !== null ? Tenant::local($v->completed_at)->format('M j') : '?').": {$v->pool?->name} by ".($v->agent?->displayName() ?? 'your technician')." ({$chem})";
            })->join("\n");

        $nextStop = RouteStop::query()
            ->whereIn('pool_id', $poolIds)
            ->where('status', 'pending')
            ->whereHas('route', fn ($q) => $q->whereDate('scheduled_date', '>=', Tenant::localToday()))
            ->with('route')
            ->get()
            ->sortBy(fn (RouteStop $s) => $s->route?->scheduled_date)
            ->first();
        $nextService = $nextStop !== null
            ? 'Next service: '.($nextStop->route?->scheduled_date?->format('l, M j') ?? 'scheduled')
            : 'No upcoming service scheduled';

        $context = "CUSTOMER: {$customer->displayName()}\n"
            ."Pools:\n{$poolSummary}\n"
            .($recentVisits !== '' ? "\nRecent visits:\n{$recentVisits}\n" : '')
            ."{$nextService}\n";

        return "THEIR SERVICE\n".$context;
    }
}
