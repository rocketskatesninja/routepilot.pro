<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\ChemicalReading;
use App\Models\User;
use App\Services\Chat\AssistantTool;

/** Office: a pool's recent water tests. */
class LookupChemistry extends AssistantTool
{
    public function name(): string
    {
        return 'lookup_chemistry';
    }

    public function description(): string
    {
        return 'Look up a pool\'s water test readings (FC, TC, pH, TA, CH, CYA, salt, TDS, phosphates, temperature, LSI): the '
            .'latest five, or the ones on a date. For what to add, use analyze_water.';
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
                'date' => ['type' => 'string', 'description' => 'A date (YYYY-MM-DD). Leave out for the latest readings.'],
            ],
            'required' => [],
        ];
    }

    /** Office, and techs (their own visits' tests unless Agent Plus). */
    public function availableTo(User $user): bool
    {
        return in_array($user->role, ['tenant_admin', 'agent'], true);
    }

    public function execute(array $params, User $user): string
    {
        $pools = $this->resolvePools($user, $params);
        if (is_string($pools)) {
            return $pools;
        }
        $pool = $this->onePool($pools);
        if (is_string($pool)) {
            return $pool;
        }

        $date = trim((string) ($params['date'] ?? ''));
        $readings = ChemicalReading::query()
            ->whereHas('serviceVisit', fn ($q) => $q->where('pool_id', $pool->id)
                ->when($this->ownVisitsOnly($user), fn ($v) => $v->where('agent_id', $user->id))
                ->when($date !== '', fn ($v) => $v->whereDate('visited_at', $date)))
            ->with('serviceVisit.agent')
            ->latest()
            ->limit(5)
            ->get();

        $name = self::link('pool', $pool->id, $pool->name).' ('.($pool->customer?->displayName() ?? 'customer').", pool_id {$pool->id})";
        if ($readings->isEmpty()) {
            return "No water tests for {$name}".($date !== '' ? " on {$date}" : '').'.';
        }

        return "Water tests for {$name}:\n".$readings->map(function (ChemicalReading $r): string {
            $vals = collect([
                'FC' => $r->free_chlorine, 'TC' => $r->total_chlorine, 'pH' => $r->ph, 'TA' => $r->alkalinity,
                'CH' => $r->calcium_hardness, 'CYA' => $r->cyanuric_acid, 'Salt' => $r->salt, 'TDS' => $r->tds,
                'Phos' => $r->phosphates, 'Temp °F' => $r->water_temperature, 'LSI' => $r->lsi_score,
            ])->filter(fn ($v): bool => $v !== null)->map(fn ($v, string $k): string => "{$k} {$v}")->join(' | ');

            return '- '.self::day($r->serviceVisit?->visited_at).' (by '.($r->serviceVisit?->agent?->displayName() ?? 'unknown')."): {$vals}";
        })->join("\n");
    }
}
