<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use App\Services\Chat\AssistantTool;

/** Office: who is going where on a day, and how far along each route is. */
class LookupSchedule extends AssistantTool
{
    public function name(): string
    {
        return 'lookup_schedule';
    }

    public function description(): string
    {
        return 'Look up the schedule for a day: each tech\'s stops in order, with their status (pending, completed, skipped, '
            .'missed). Optionally just one tech. Links to that day on the Schedule page.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'date' => ['type' => 'string', 'description' => self::DAY_HELP],
                'tech_name' => ['type' => 'string', 'description' => 'Just this tech\'s route (optional).'],
            ],
            'required' => [],
        ];
    }

    /** Office, and techs: the Schedule page shows every route to both. */
    public function availableTo(User $user): bool
    {
        return in_array($user->role, ['tenant_admin', 'agent'], true);
    }

    public function execute(array $params, User $user): string
    {
        $date = $this->resolveDay((string) ($params['date'] ?? ''));
        if ($date === null) {
            return 'Give the day as a weekday ("monday"), "today", "tomorrow", or a date as YYYY-MM-DD.';
        }
        $tech = trim((string) ($params['tech_name'] ?? ''));
        if ($tech !== '' && ($problem = $this->nameProblem($tech)) !== null) {
            return $problem;
        }

        $routes = Route::query()
            ->where('tenant_id', (int) $user->tenant_id)
            ->whereDate('scheduled_date', $date->toDateString())
            ->when($tech !== '', fn ($q) => $q->whereHas('agent', fn ($a) => $this->whereNameLike($a, $tech)))
            ->with(['agent', 'stops' => fn ($s) => $s->orderBy('stop_order'), 'stops.pool.customer'])
            ->get();

        $day = self::link('day', $date->format('Ymd'), $date->format('l, M j'));
        if ($routes->isEmpty()) {
            return "Nothing scheduled on {$day}".($tech !== '' ? " for \"{$tech}\"" : '').'.';
        }

        return "Schedule for {$day}:\n".$routes->map(function (Route $route): string {
            $stops = $route->stops;
            $counts = $stops->countBy('status')->map(fn (int $n, string $s): string => "{$n} {$s}")->join(', ');
            $shown = $stops->take(self::ROWS);

            return ($route->agent !== null ? self::link('agent', $route->agent->id, $route->agent->displayName()) : 'Unassigned')
                .": {$stops->count()} stops ({$counts})\n"
                .$shown->map(fn (RouteStop $s): string => "  {$s->stop_order}. "
                    .($s->pool !== null ? self::link('pool', $s->pool->id, $s->pool->name) : 'a removed pool')
                    .' — '.($s->pool?->customer?->displayName() ?? '?')." — {$s->status}")->join("\n")
                .self::more($stops->count(), $shown->count());
        })->join("\n\n");
    }
}
