<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\RouteStop;
use App\Services\Chat\AiTool;
use Illuminate\Support\Carbon;

/**
 * Skip a single scheduled stop for today or a specific date.
 */
class SkipStop extends AiTool
{
    public function name(): string
    {
        return 'skip_stop';
    }

    public function description(): string
    {
        return 'Skip a scheduled stop for today or a specific date. '
            .'Use this when the tenant asks to skip or cancel a specific visit.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'customer_name' => ['type' => 'string', 'description' => 'The customer\'s name'],
                'pool_name' => ['type' => 'string', 'description' => 'The pool name (optional)'],
                'reason' => ['type' => 'string', 'description' => 'Reason for skipping'],
                'date' => ['type' => 'string', 'description' => 'Date to skip (YYYY-MM-DD). Defaults to today.'],
            ],
            'required' => ['customer_name'],
        ];
    }

    public function execute(array $params, int $tenantId): string
    {
        $customerName = trim((string) ($params['customer_name'] ?? ''));
        $poolName = trim((string) ($params['pool_name'] ?? ''));
        $reason = trim((string) ($params['reason'] ?? ''));
        $date = trim((string) ($params['date'] ?? '')) ?: Carbon::now()->toDateString();

        $stop = RouteStop::query()
            ->where('status', 'pending')
            ->whereHas('route', fn ($q) => $q->where('tenant_id', $tenantId)->whereDate('scheduled_date', $date))
            ->whereHas('pool.customer', fn ($q) => $this->whereNameLike($q, $customerName))
            ->when($poolName !== '', fn ($q) => $q->whereHas('pool', fn ($p) => $p->where('name', 'like', "%{$poolName}%")))
            ->with('pool.customer')
            ->first();

        if ($stop === null) {
            return "No pending stop found for \"{$customerName}\" on {$date}.";
        }

        $stop->update([
            'status' => 'skipped',
            'skip_reason' => $reason !== '' ? $reason : 'Skipped via AI assistant',
        ]);

        return "Done! Skipped {$stop->pool?->customer?->displayName()}'s \"{$stop->pool?->name}\" stop on {$date}.";
    }
}
