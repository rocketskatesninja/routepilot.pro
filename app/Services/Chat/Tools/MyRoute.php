<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\ChemicalReading;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use App\Models\WorkOrder;
use App\Policies\WorkOrderPolicy;
use App\Services\Chat\AssistantTool;

/**
 * A tech's own route for a day, stop by stop, with what the field app shows
 * on each: the customer and their phone, address, gate code, access notes,
 * the service, the last water test and the pool's open work orders.
 */
class MyRoute extends AssistantTool
{
    public function name(): string
    {
        return 'my_route';
    }

    public function description(): string
    {
        return 'Your own route for a day: each stop in order with its status, the customer and phone, address, gate code, '
            .'access notes, the service, the last water test, and any open work orders on that pool.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'date' => ['type' => 'string', 'description' => self::DAY_HELP],
            ],
            'required' => [],
        ];
    }

    public function availableTo(User $user): bool
    {
        return $user->role === 'agent';
    }

    public function execute(array $params, User $user): string
    {
        $date = $this->resolveDay((string) ($params['date'] ?? ''));
        if ($date === null) {
            return 'Give the day as a weekday ("monday"), "today", "tomorrow", or a date as YYYY-MM-DD.';
        }

        $route = Route::query()
            ->where('tenant_id', (int) $user->tenant_id)
            ->where('agent_id', $user->id)
            ->whereDate('scheduled_date', $date->toDateString())
            ->with([
                'stops' => fn ($s) => $s->orderBy('stop_order'),
                'stops.pool.customer',
                'stops.pool.serviceLocation',
                'stops.serviceSubscription.serviceType',
            ])
            ->first();

        $day = self::link('day', $date->format('Ymd'), $date->format('l, M j'));
        if ($route === null || $route->stops->isEmpty()) {
            return "You have no stops on {$day}.";
        }

        $poolIds = $route->stops->pluck('pool_id')->filter()->unique()->values();
        $orders = WorkOrder::query()->where('tenant_id', (int) $user->tenant_id)->where('status', 'open')
            ->whereIn('pool_id', $poolIds)->with('assignee')->get()->groupBy('pool_id');
        $runsTheList = WorkOrderPolicy::runsTheList($user);

        return "Your route on {$day} ({$route->stops->count()} stops, ".$route->stops->countBy('status')->map(fn (int $n, string $s): string => "{$n} {$s}")->join(', ')."):\n"
            .$route->stops->map(function (RouteStop $stop) use ($orders, $runsTheList, $user): string {
                $pool = $stop->pool;
                if ($pool === null) {
                    return "{$stop->stop_order}. a removed pool — {$stop->status}";
                }
                $loc = $pool->serviceLocation;
                $last = ChemicalReading::query()->whereHas('serviceVisit', fn ($q) => $q->where('pool_id', $pool->id))->latest()->first();
                $workOrders = ($orders->get($pool->id) ?? collect())->map(fn (WorkOrder $o): string => '    - '
                    .($runsTheList || $o->assigned_to === $user->id ? self::link('workorder', $o->id, $o->title) : $o->title)
                    .($o->urgent ? ' (URGENT)' : '').($o->due_on !== null ? ', due '.$o->due_on->format('M j') : '')
                    .', '.($o->assigned_to === $user->id ? 'yours' : 'assigned to '.($o->assignee?->displayName() ?? 'nobody'))
                    .(($d = self::note($o->details)) !== null ? " — {$d}" : ''));

                return collect([
                    "{$stop->stop_order}. {$pool->name} — ".($pool->customer?->displayName() ?? '?')." — {$stop->status}"
                        .' — '.($stop->serviceSubscription?->serviceType?->getAttribute('name') ?? 'extra visit'),
                    ($address = collect([$loc?->getAttribute('address_line1'), $loc?->getAttribute('city')])->filter()->join(', ')) !== '' ? "   Address: {$address}" : null,
                    $pool->customer?->phone !== null ? "   Phone: {$pool->customer->phone}" : null,
                    $loc?->getAttribute('gate_code') ? '   Gate code: '.$loc->getAttribute('gate_code') : null,
                    ($access = self::note($loc?->getAttribute('access_notes'))) !== null ? "   Access notes: {$access}" : null,
                    "   {$pool->type}, ".($pool->volume_gallons !== null ? number_format($pool->volume_gallons).' gal' : 'size unknown').", {$pool->sanitizer_type}",
                    $last !== null ? '   Last test ('.self::day($last->created_at, 'M j')."): FC {$last->free_chlorine}, pH {$last->ph}, TA {$last->alkalinity}".($last->lsi_score !== null ? ", LSI {$last->lsi_score}" : '') : null,
                    $workOrders->isNotEmpty() ? "   Open work orders:\n".$workOrders->join("\n") : null,
                ])->filter()->join("\n");
            })->join("\n");
    }
}
