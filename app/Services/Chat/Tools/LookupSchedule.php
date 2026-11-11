<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Chat\AssistantTool;
use Illuminate\Support\Carbon;

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
                'date' => ['type' => 'string', 'description' => 'The day: a weekday as a word ("monday" = the coming Monday), "today", "tomorrow", "yesterday", or a date as YYYY-MM-DD. Pass weekdays as words: the date is worked out for you. Leave out for today.'],
                'tech_name' => ['type' => 'string', 'description' => 'Just this tech\'s route (optional).'],
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

    /**
     * The day asked for, in the company's time zone: a weekday word is the
     * coming one (today if it's today), so the model never does date sums.
     */
    private function resolveDay(string $raw): ?Carbon
    {
        $today = Tenant::localToday();
        $raw = (string) preg_replace('/^(next|this|on|coming)\s+/', '', strtolower(trim($raw)));

        if ($raw === '' || $raw === 'today') {
            return $today;
        }
        if ($raw === 'tomorrow' || $raw === 'yesterday') {
            return $today->copy()->addDays($raw === 'tomorrow' ? 1 : -1);
        }
        foreach (['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as $index => $name) {
            if (strlen($raw) >= 3 && str_starts_with($name, $raw)) {
                return $today->dayOfWeek === $index ? $today : $today->copy()->next($index);
            }
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return Carbon::parse($raw, $today->getTimezone());
        }

        return null;
    }
}
