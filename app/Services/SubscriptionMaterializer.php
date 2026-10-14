<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\Tenant;
use App\Support\Weekday;
use Carbon\Carbon;

/**
 * The one owner of a plan's dated schedule: turns active ServiceSubscriptions
 * into RouteStops, and re-syncs a plan's upcoming stops whenever it changes.
 *
 * Rules:
 *  - One window everywhere: from the company's today through WINDOW_WEEKS ahead.
 *  - "Today" is the company's local date, never the server's; all date math is
 *    done on plain dates so time zones can't shift a visit by a day.
 *  - Idempotent: the key is "subscription_id|date", so re-running never
 *    double-creates, and one pool can hold two same-day stops from two plans
 *    (e.g. a clean + a chemistry check).
 *  - Plans without a tech still get stops, on that day's "unassigned" route.
 *  - Dates inside a vacation hold are skipped; the plan resumes after it.
 */
class SubscriptionMaterializer
{
    /** How far ahead plans are turned into route stops. */
    public const WINDOW_WEEKS = 8;

    /**
     * Create any missing upcoming stops for a company's active plans.
     *
     * @param  string|null  $through  Last date (YYYY-MM-DD) when the calendar asks to
     *                                look further ahead; capped at 6 months.
     */
    public function run(int $tenantId, ?string $through = null): int
    {
        $start = $this->today($tenantId);
        $end = $through !== null
            ? Carbon::parse($through)->startOfDay()->min($start->copy()->addMonths(6))
            : $start->copy()->addWeeks(self::WINDOW_WEEKS);

        // Explicitly tenant-filtered and scope-bypassed: this runs from the nightly
        // job (no tenant bound) as well as from HTTP, and must behave the same.
        $subs = ServiceSubscription::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->get();
        if ($subs->isEmpty()) {
            return 0;
        }

        // Every existing stop in the window for these plans — the idempotency guard.
        $existingKeys = RouteStop::query()
            ->whereHas('route', fn ($q) => $q->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()]))
            ->whereIn('service_subscription_id', $subs->pluck('id'))
            ->with(['route' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'scheduled_date')])
            ->get()
            ->mapWithKeys(fn (RouteStop $s) => [$s->service_subscription_id.'|'.$s->route->scheduled_date->toDateString() => true])
            ->all();

        $created = 0;
        /** @var array<string, Route> $routes */
        $routes = [];

        foreach ($subs as $sub) {
            foreach ($this->projectDates($sub, $start, $end) as $date) {
                $dateStr = $date->toDateString();
                if ($sub->isOnHold($date) || isset($existingKeys[$sub->id.'|'.$dateStr])) {
                    continue;
                }

                $agentId = $sub->assigned_agent_id;
                $route = $routes[($agentId ?? 'unassigned').'|'.$dateStr] ??= $this->routeFor($tenantId, $agentId, $dateStr);

                RouteStop::create([
                    'route_id' => $route->id,
                    'pool_id' => $sub->pool_id,
                    'service_subscription_id' => $sub->id,
                    'stop_order' => ((int) $route->stops()->max('stop_order')) + 1,
                    'status' => 'pending',
                ]);

                $existingKeys[$sub->id.'|'.$dateStr] = true;
                $created++;
            }
        }

        return $created;
    }

    /**
     * Bring one plan's upcoming stops in line with the plan as it is now: clear
     * them (completed/skipped history is never touched) and schedule afresh.
     */
    public function resync(ServiceSubscription $sub): int
    {
        $this->clearUpcoming($sub);

        return $this->run((int) $sub->getAttribute('tenant_id'));
    }

    /** Remove a plan's upcoming pending stops, and any future routes left empty. */
    public function clearUpcoming(ServiceSubscription $sub): void
    {
        $tenantId = (int) $sub->getAttribute('tenant_id');

        $stops = RouteStop::query()
            ->where('service_subscription_id', $sub->id)
            ->where('status', 'pending')
            ->whereHas('route', fn ($q) => $q->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('scheduled_date', '>=', $this->today($tenantId)->toDateString()))
            ->get(['id', 'route_id']);

        RouteStop::query()->whereKey($stops->pluck('id'))->delete();
        Route::query()->withoutGlobalScopes()
            ->whereKey($stops->pluck('route_id')->unique())
            ->whereDoesntHave('stops')
            ->delete();
    }

    /**
     * A plan's visit dates in [start, end]. All values are plain dates.
     *
     * @return list<Carbon>
     */
    protected function projectDates(ServiceSubscription $sub, Carbon $start, Carbon $end): array
    {
        $startsOn = $sub->starts_on !== null ? Carbon::parse($sub->starts_on->toDateString()) : null;

        if ($sub->frequency === 'one_time') {
            return $startsOn !== null && $startsOn->betweenIncluded($start, $end) ? [$startsOn] : [];
        }

        $from = $startsOn !== null ? $start->copy()->max($startsOn) : $start->copy();
        $to = $sub->ends_on !== null ? $end->copy()->min(Carbon::parse($sub->ends_on->toDateString())) : $end->copy();
        if ($from->gt($to)) {
            return [];
        }

        $dates = [];

        if ($sub->frequency === 'monthly') {
            // The chosen day each month, clamped to short months (31 → Jun 30).
            $dom = $sub->day_of_month ?? $startsOn->day ?? 1;
            for ($month = $from->copy()->startOfMonth(); $month->lte($to); $month->addMonthNoOverflow()) {
                $date = $month->copy()->day(min($dom, $month->daysInMonth));
                if ($date->betweenIncluded($from, $to)) {
                    $dates[] = $date;
                }
            }

            return $dates;
        }

        // Weekly / every 2 weeks, on the preferred weekday.
        $weekday = Weekday::MAP[$sub->preferred_day ?? ''] ?? $startsOn->dayOfWeek ?? Carbon::MONDAY;
        $date = $from->copy();
        while ($date->dayOfWeek !== $weekday) {
            $date->addDay();
        }

        if ($sub->frequency === 'biweekly') {
            // Count fortnights from the first visit on/after the plan's start date.
            $anchor = Carbon::parse(($startsOn ?? $sub->created_at ?? $from)->toDateString());
            while ($anchor->dayOfWeek !== $weekday) {
                $anchor->addDay();
            }
            if (((int) round(abs($anchor->diffInDays($date))) / 7) % 2 === 1) {
                $date->addWeek();
            }
        }

        for (; $date->lte($to); $date->addWeeks($sub->frequency === 'biweekly' ? 2 : 1)) {
            $dates[] = $date->copy();
        }

        return $dates;
    }

    /** The company's today as a plain date (midnight, app time zone). */
    private function today(int $tenantId): Carbon
    {
        $tenant = Tenant::query()->find($tenantId);
        $local = $tenant instanceof Tenant ? $tenant->today() : Tenant::localToday();

        return Carbon::parse($local->toDateString());
    }

    /** The agent's (or the unassigned) route for a date, created if missing. */
    private function routeFor(int $tenantId, ?int $agentId, string $date): Route
    {
        $route = Route::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->when($agentId !== null, fn ($q) => $q->where('agent_id', $agentId), fn ($q) => $q->whereNull('agent_id'))
            ->whereDate('scheduled_date', $date)
            ->first();

        if ($route === null) {
            // tenant_id is deliberately not fillable (charter: privilege fields via forceFill).
            $route = new Route(['agent_id' => $agentId, 'scheduled_date' => $date, 'status' => 'scheduled']);
            $route->forceFill(['tenant_id' => $tenantId])->save();
        }

        return $route;
    }
}
