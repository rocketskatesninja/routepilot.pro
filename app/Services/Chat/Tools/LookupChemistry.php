<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\ChemicalReading;
use App\Models\Pool;
use App\Services\Chat\AiTool;

/**
 * Get chemical/water-test readings for a customer's pool — a specific date
 * or the most recent.
 */
class LookupChemistry extends AiTool
{
    public function name(): string
    {
        return 'lookup_chemistry';
    }

    public function description(): string
    {
        return 'Look up chemical/water test readings for a pool — FC, pH, TA, CH, CYA, salt, LSI, etc. '
            .'Use when asked about water chemistry, readings, or test results for a specific pool or date.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'customer_name' => ['type' => 'string', 'description' => 'Customer name (fuzzy match)'],
                'pool_name' => ['type' => 'string', 'description' => 'Pool name (optional if customer has one pool)'],
                'date' => ['type' => 'string', 'description' => 'Specific date (YYYY-MM-DD). Omit for most recent readings.'],
            ],
            'required' => ['customer_name'],
        ];
    }

    public function execute(array $params, int $tenantId): string
    {
        $customerName = trim((string) ($params['customer_name'] ?? ''));
        $poolName = trim((string) ($params['pool_name'] ?? ''));
        $date = trim((string) ($params['date'] ?? ''));

        $pools = Pool::query()
            ->whereHas('customer', fn ($q) => $this->whereNameLike($q, $customerName))
            ->when($poolName !== '', fn ($q) => $q->where('name', 'like', "%{$poolName}%"))
            ->with('customer')
            ->get();

        if ($pools->isEmpty()) {
            return "No pools found for customer \"{$customerName}\".";
        }
        if ($pools->count() > 1 && $poolName === '') {
            $list = $pools->map(fn (Pool $p): string => "- {$p->name}")->join("\n");

            return "Multiple pools found for {$pools->first()->customer?->displayName()}. Please specify:\n{$list}";
        }

        $pool = $pools->first();

        $readings = ChemicalReading::query()
            ->whereHas('serviceVisit', fn ($q) => $q->where('pool_id', $pool->id))
            ->when($date !== '', fn ($q) => $q->whereHas('serviceVisit', fn ($v) => $v->whereDate('visited_at', $date)))
            ->with('serviceVisit.agent')
            ->latest()
            ->limit(5)
            ->get();

        if ($readings->isEmpty()) {
            $dateNote = $date !== '' ? " on {$date}" : '';

            return "No chemical readings found for {$pool->customer?->displayName()}'s \"{$pool->name}\"{$dateNote}.";
        }

        $header = "Chemical readings for **{$pool->customer?->displayName()}**'s **{$pool->name}** [id:{$pool->id}]:";

        $rows = $readings->map(function (ChemicalReading $r): string {
            $visit = $r->serviceVisit;
            $when = $visit?->visited_at?->format('M j, Y') ?? 'Unknown date';
            $agent = $visit?->agent?->displayName() ?? 'Unknown';

            $vals = collect([
                $r->free_chlorine !== null ? "FC: {$r->free_chlorine}" : null,
                $r->total_chlorine !== null ? "TC: {$r->total_chlorine}" : null,
                $r->ph !== null ? "pH: {$r->ph}" : null,
                $r->alkalinity !== null ? "TA: {$r->alkalinity}" : null,
                $r->calcium_hardness !== null ? "CH: {$r->calcium_hardness}" : null,
                $r->cyanuric_acid !== null ? "CYA: {$r->cyanuric_acid}" : null,
                $r->salt !== null ? "Salt: {$r->salt}" : null,
                $r->tds !== null ? "TDS: {$r->tds}" : null,
                $r->phosphates !== null ? "Phos: {$r->phosphates}" : null,
                $r->water_temperature !== null ? "Temp: {$r->water_temperature}°F" : null,
                $r->lsi_score !== null ? "LSI: {$r->lsi_score}" : null,
            ])->filter()->join(' | ');

            return "**{$when}** (by {$agent})\n  {$vals}";
        })->join("\n\n");

        return "{$header}\n\n{$rows}";
    }
}
