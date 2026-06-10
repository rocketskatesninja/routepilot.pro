<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Services\Chat\AiTool;

/**
 * Get service-visit history for a customer or pool — dates, agent, status,
 * notes, and whether readings/treatments exist.
 */
class LookupServiceHistory extends AiTool
{
    public function name(): string
    {
        return 'lookup_service_history';
    }

    public function description(): string
    {
        return 'Look up service visit history for a customer or pool. '
            .'Shows dates, which agent did the visit, status, and notes. '
            .'Use when asked about past visits, last service date, or visit frequency.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'customer_name' => ['type' => 'string', 'description' => 'Customer name (fuzzy match)'],
                'pool_name' => ['type' => 'string', 'description' => 'Pool name (optional — omit to see all pools)'],
                'limit' => ['type' => 'integer', 'description' => 'Number of recent visits to return (default 5, max 20)'],
            ],
            'required' => ['customer_name'],
        ];
    }

    public function execute(array $params, int $tenantId): string
    {
        $customerName = trim((string) ($params['customer_name'] ?? ''));
        $poolName = trim((string) ($params['pool_name'] ?? ''));
        $limit = min(is_numeric($params['limit'] ?? null) ? (int) $params['limit'] : 5, 20);

        $pools = Pool::query()
            ->whereHas('customer', fn ($q) => $this->whereNameLike($q, $customerName))
            ->when($poolName !== '', fn ($q) => $q->where('name', 'like', "%{$poolName}%"))
            ->with('customer')
            ->get();

        if ($pools->isEmpty()) {
            return "No pools found for customer \"{$customerName}\".";
        }

        $displayName = $pools->first()->customer?->displayName() ?? $customerName;

        $visits = ServiceVisit::query()
            ->whereIn('pool_id', $pools->pluck('id'))
            ->with('pool', 'agent', 'chemicalReading', 'treatments')
            ->latest('visited_at')
            ->limit($limit)
            ->get();

        if ($visits->isEmpty()) {
            return "No service visits found for {$displayName}.";
        }

        $rows = $visits->map(function (ServiceVisit $v): string {
            $when = $v->visited_at?->format('M j, Y g:ia') ?? 'Unknown date';
            $agent = $v->agent?->displayName() ?? 'Unknown';

            $extras = collect([
                $v->chemicalReading !== null ? 'Readings recorded' : null,
                $v->treatments->isNotEmpty() ? $v->treatments->count().' treatment(s)' : null,
                $v->paid_at !== null ? 'Paid' : ($v->status === 'completed' ? 'Unpaid' : null),
            ])->filter()->join(' | ');

            $line = "- **{$when}** — {$v->pool?->name} — {$agent} — ".ucfirst($v->status);
            if ($extras !== '') {
                $line .= "\n  {$extras}";
            }
            if ($v->notes !== null) {
                $line .= "\n  Notes: {$v->notes}";
            }

            return $line;
        })->join("\n");

        return "Service history for **{$displayName}** (last {$visits->count()} visits):\n\n{$rows}";
    }
}
