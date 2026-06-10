<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\Pool;
use App\Models\ServiceSubscription;
use App\Services\Chat\AiTool;

/**
 * Find pool details — specs, equipment, location, and active services —
 * searching by customer name, pool name, or both.
 */
class LookupPool extends AiTool
{
    public function name(): string
    {
        return 'lookup_pool';
    }

    public function description(): string
    {
        return 'Look up pool details — volume, type, equipment, surface, sanitizer, location, and active services. '
            .'Use when asked about pool specs, equipment, volume, or to compare pools.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'customer_name' => ['type' => 'string', 'description' => 'Customer name to filter by (optional)'],
                'pool_name' => ['type' => 'string', 'description' => 'Pool name to search for (optional)'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $params, int $tenantId): string
    {
        $customerName = trim((string) ($params['customer_name'] ?? ''));
        $poolName = trim((string) ($params['pool_name'] ?? ''));

        $pools = Pool::query()
            ->with('customer', 'serviceLocation', 'subscriptions.serviceType', 'subscriptions.agent')
            ->when($customerName !== '', fn ($q) => $q->whereHas('customer', fn ($c) => $this->whereNameLike($c, $customerName)))
            ->when($poolName !== '', fn ($q) => $q->where('name', 'like', "%{$poolName}%"))
            ->limit(10)
            ->get();

        if ($pools->isEmpty()) {
            return 'No pools found matching that search.';
        }

        return $pools->map(function (Pool $p): string {
            $vol = $p->volume_gallons !== null ? number_format($p->volume_gallons).' gallons' : 'unknown';
            $loc = $p->serviceLocation;
            $address = $loc !== null
                ? collect([$loc->getAttribute('address_line1'), $loc->getAttribute('city'), $loc->getAttribute('state'), $loc->getAttribute('zip')])->filter()->join(', ')
                : '';

            $equipment = collect([
                $p->has_heater ? 'Heater' : null,
                $p->has_automation ? 'Automation' : null,
                $p->has_pool_cleaner ? 'Cleaner' : null,
                $p->has_cover ? 'Cover' : null,
                $p->has_water_feature ? 'Water Feature' : null,
                $p->has_auto_fill ? 'Auto-Fill' : null,
            ])->filter();

            $subs = $p->subscriptions->where('status', 'active')->map(function (ServiceSubscription $s): string {
                $agent = $s->agent?->displayName() ?? 'unassigned';
                $service = $s->serviceType?->getAttribute('name') ?? 'service';

                return "  - {$service} ({$s->scheduleLabel()}) → {$agent}";
            })->join("\n");

            return collect([
                "**{$p->name}** [id:{$p->id}] — {$p->customer?->displayName()} [id:{$p->customer?->id}]",
                "Type: {$p->type} | Volume: {$vol}",
                'Surface: '.($p->surface_type ?? 'unknown').' | Sanitizer: '.$p->sanitizer_type,
                'Filter: '.($p->filter_type ?? 'unknown').' | Pump: '.($p->pump_type ?? 'unknown'),
                $equipment->isNotEmpty() ? 'Equipment: '.$equipment->join(', ') : null,
                $address !== '' ? "Location: {$address}" : null,
                $loc !== null && $loc->getAttribute('gate_code') ? "Gate code: {$loc->getAttribute('gate_code')}" : null,
                $loc !== null && $loc->getAttribute('access_notes') ? "Access: {$loc->getAttribute('access_notes')}" : null,
                $p->notes !== null ? "Notes: {$p->notes}" : null,
                $subs !== '' ? "Active services:\n{$subs}" : 'No active services.',
            ])->filter()->join("\n");
        })->join("\n\n---\n\n");
    }
}
