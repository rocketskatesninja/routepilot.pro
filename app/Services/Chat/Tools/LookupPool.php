<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\Pool;
use App\Models\ServiceSubscription;
use App\Models\User;
use App\Services\Chat\AssistantTool;

/** Office: a pool's specs, equipment, access details, targets and plans. */
class LookupPool extends AssistantTool
{
    public function name(): string
    {
        return 'lookup_pool';
    }

    public function description(): string
    {
        return 'Look up a customer\'s pools: volume, type, surface, sanitizer, filter, pump, equipment, address, gate code, '
            .'access notes, custom water targets and plans (service, day, price, tech).';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'customer_name' => ['type' => 'string', 'description' => 'The customer\'s name (part of it is fine).'],
                'pool_name' => ['type' => 'string', 'description' => 'The pool\'s name, when they have more than one (optional).'],
                'pool_id' => ['type' => 'integer', 'description' => 'The pool\'s id, when you already know it (optional).'],
            ],
            'required' => [],
        ];
    }

    public function availableTo(User $user): bool
    {
        return $user->role === 'tenant_admin';
    }

    public function execute(array $params, User $user): string
    {
        $pools = $this->resolvePools($user, $params);
        if (is_string($pools)) {
            return $pools;
        }
        $pools->load(['serviceLocation', 'subscriptions' => fn ($s) => $s->whereIn('status', ['active', 'paused']), 'subscriptions.serviceType', 'subscriptions.agent']);

        return $pools->map(function (Pool $p): string {
            $loc = $p->serviceLocation;
            $address = $loc !== null ? collect([$loc->getAttribute('address_line1'), $loc->getAttribute('city'), $loc->getAttribute('state'), $loc->getAttribute('zip')])->filter()->join(', ') : '';
            $equipment = collect([
                $p->has_heater ? 'heater' : null,
                $p->has_automation ? 'automation' : null,
                $p->has_pool_cleaner ? 'cleaner' : null,
                $p->has_cover ? 'cover' : null,
                $p->has_water_feature ? 'water feature' : null,
                $p->has_auto_fill ? 'auto-fill' : null,
            ])->filter();
            $targets = collect($p->custom_target_ranges ?? [])->map(fn ($r, $k): string => "{$k} ".($r['min'] ?? '?').'–'.($r['max'] ?? '?'))->join(', ');
            $plans = $p->subscriptions->map(fn (ServiceSubscription $s): string => '  - '.$this->planLine($s))->join("\n");

            return collect([
                self::link('pool', $p->id, $p->name)." (pool_id {$p->id}) — ".self::link('customer', (int) $p->customer_id, $p->customer?->displayName() ?? 'Customer'),
                "Type: {$p->type} | Volume: ".($p->volume_gallons !== null ? number_format($p->volume_gallons).' gallons' : 'unknown'),
                'Surface: '.($p->surface_type ?? 'unknown').' | Sanitizer: '.$p->sanitizer_type,
                'Filter: '.($p->filter_type ?? 'unknown').' | Pump: '.($p->pump_type ?? 'unknown'),
                $equipment->isNotEmpty() ? 'Equipment: '.$equipment->join(', ') : null,
                $address !== '' ? "Address: {$address}" : null,
                $loc?->getAttribute('gate_code') ? 'Gate code: '.$loc->getAttribute('gate_code') : null,
                ($access = self::note($loc?->getAttribute('access_notes'))) !== null ? "Access notes: {$access}" : null,
                ($notes = self::note($p->notes)) !== null ? "Pool notes: {$notes}" : null,
                $targets !== '' ? "Custom water targets: {$targets}" : null,
                $plans !== '' ? "Plans:\n{$plans}" : 'No active plan.',
            ])->filter()->join("\n");
        })->join("\n\n---\n\n");
    }
}
