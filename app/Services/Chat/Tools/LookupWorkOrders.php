<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Chat\AssistantTool;

/** Office: open (or finished) work orders, urgent and overdue first. */
class LookupWorkOrders extends AssistantTool
{
    public function name(): string
    {
        return 'lookup_work_orders';
    }

    public function description(): string
    {
        return 'Look up work orders (repairs and one-off jobs): open ones by default, or the done ones. Shows urgency, due '
            .'date, pool, who it\'s assigned to and the price.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['open', 'done'], 'description' => 'Open (default) or done.'],
                'customer_name' => ['type' => 'string', 'description' => 'Just this customer\'s work orders (optional).'],
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
        $done = ($params['status'] ?? 'open') === 'done';
        $name = trim((string) ($params['customer_name'] ?? ''));
        if ($name !== '' && ($problem = $this->nameProblem($name)) !== null) {
            return $problem;
        }

        $query = WorkOrder::query()
            ->where('work_orders.tenant_id', (int) $user->tenant_id)
            ->where('status', $done ? 'done' : 'open')
            ->when($name !== '', fn ($q) => $q->whereHas('pool.customer', fn ($c) => $this->whereNameLike($c, $name)));
        $total = (clone $query)->count();
        $orders = $query->with('pool.customer', 'assignee')
            ->when(! $done, fn ($q) => $q->orderByDesc('urgent')->orderByRaw('due_on is null')->orderBy('due_on'))
            ->when($done, fn ($q) => $q->latest('completed_at'))
            ->limit(self::ROWS)
            ->get();

        if ($orders->isEmpty()) {
            return 'No '.($done ? 'finished' : 'open').' work orders'.($name !== '' ? " for \"{$name}\"" : '').'.';
        }

        return ($done ? 'Finished' : 'Open')." work orders ({$total}):\n".$orders->map(fn (WorkOrder $w): string => '- '
            .self::link('workorder', $w->id, $w->title)
            .($w->urgent ? ' — URGENT' : '')
            .($w->due_on !== null ? ' — due '.$w->due_on->format('M j').($w->isOverdue() ? ' (overdue)' : '') : '')
            .($w->pool !== null ? ' — '.$w->pool->name.' ('.($w->pool->customer?->displayName() ?? '?').')' : '')
            .' — '.($w->assignee?->displayName() ?? 'not assigned')
            .($w->price !== null ? ' — $'.number_format((float) $w->price, 2) : '')
            .(($d = self::note($w->details)) !== null ? "\n  Details: {$d}" : ''))->join("\n")
            .self::more($total, $orders->count());
    }
}
