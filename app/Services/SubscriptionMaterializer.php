<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use Carbon\Carbon;

/**
 * Creates concrete RouteStops from active ServiceSubscriptions.
 *
 * For each active subscription with an assigned agent, projects the
 * cadence (weekly / biweekly / monthly) across the window and creates a
 * RouteStop for every occurrence that doesn't already exist.
 *
 * Idempotent — dedup key is "subscription_id|date", so re-running never
 * double-creates, and one pool CAN hold two same-day stops from two
 * subscriptions (e.g. clean + chemistry check by different agents).
 *
 * New-build changes vs legacy:
 *  - Cadence details (week_type / day_of_month) read from the
 *    frequency_details JSON column.
 *  - Dates inside a subscription's vacation-hold window are skipped;
 *    materialization auto-resumes after hold_ends_at.
 */
class SubscriptionMaterializer
{
    /**
     * Materialize upcoming stops for a tenant.
     *
     * @param  string|null  $through  End date (YYYY-MM-DD). Defaults to 8 weeks
     *                                ahead; capped at 6 months so calendar
     *                                navigation can extend on demand safely.
     */
    public function run(int $tenantId, ?string $through = null): void
    {
        $start = now()->startOfDay();
        $maxEnd = $start->copy()->addMonths(6);
        $end = $through ? Carbon::parse($through)->endOfDay()->min($maxEnd) : $start->copy()->addWeeks(8);

        // Explicitly tenant-filtered and scope-bypassed: this runs from the
        // nightly job (no tenant bound) as well as HTTP, and must behave
        // identically in both.
        $subs = ServiceSubscription::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNotNull('assigned_agent_id')
            ->get();
        if ($subs->isEmpty()) {
            return;
        }

        // Every existing stop in the window for these subscriptions,
        // keyed "subscription_id|YYYY-MM-DD" — the idempotency guard.
        $subIds = $subs->pluck('id');
        $existingKeys = RouteStop::query()
            ->whereHas('route', fn ($q) => $q
                ->where('tenant_id', $tenantId)
                ->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()]))
            ->whereIn('service_subscription_id', $subIds)
            ->with('route:id,scheduled_date')
            ->get()
            ->mapWithKeys(fn ($s) => [$s->service_subscription_id.'|'.$s->route->scheduled_date->toDateString() => true])
            ->all();

        /** @var array<string, Route> $routes */
        $routes = [];

        foreach ($subs as $sub) {
            foreach ($this->projectDates($sub, $start, $end) as $date) {
                if ($sub->isOnHold($date)) {
                    continue; // vacation hold — auto-resumes after the window
                }

                $dateStr = $date->toDateString();
                $key = $sub->id.'|'.$dateStr;
                if (isset($existingKeys[$key])) {
                    continue;
                }

                $routeKey = $sub->assigned_agent_id.'|'.$dateStr;
                if (! isset($routes[$routeKey])) {
                    $route = Route::query()->withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('agent_id', $sub->assigned_agent_id)
                        ->whereDate('scheduled_date', $dateStr)
                        ->first();

                    if (! $route) {
                        // tenant_id is deliberately not fillable (charter:
                        // privilege fields via forceFill at controlled sites).
                        $route = new Route(['agent_id' => $sub->assigned_agent_id, 'scheduled_date' => $dateStr, 'status' => 'scheduled']);
                        $route->forceFill(['tenant_id' => $tenantId])->save();
                    }

                    $routes[$routeKey] = $route;
                }
                $route = $routes[$routeKey];

                RouteStop::create([
                    'route_id' => $route->id,
                    'pool_id' => $sub->pool_id,
                    'service_subscription_id' => $sub->id,
                    'stop_order' => ((int) $route->stops()->max('stop_order')) + 1,
                    'status' => 'pending',
                ]);

                $existingKeys[$key] = true;
            }
        }
    }

    /**
     * Project a subscription's cadence into concrete dates in [start, end].
     *
     * @return list<Carbon>
     */
    protected function projectDates(ServiceSubscription $sub, Carbon $start, Carbon $end): array
    {
        $dates = [];
        $frequency = $sub->frequency ?? 'weekly';
        $details = $sub->frequency_details ?? [];

        if ($frequency === 'monthly') {
            // One occurrence per month on the chosen day, clamped to month length.
            $dom = (int) ($details['day_of_month'] ?? 1);
            $cursor = $start->copy()->startOfMonth();
            while ($cursor <= $end) {
                $candidate = $cursor->copy()->day(min($dom, $cursor->daysInMonth));
                if ($candidate >= $start && $candidate <= $end) {
                    $dates[] = $candidate;
                }
                $cursor->addMonth()->startOfMonth();
            }

            return $dates;
        }

        // Weekly / biweekly — walk days, match the preferred weekday.
        $dayMap = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
        $targetDow = $dayMap[$sub->preferred_day ?? 'monday'] ?? 1;

        $cursor = $start->copy();
        while ($cursor <= $end) {
            if ($cursor->dayOfWeek === $targetDow) {
                if ($frequency === 'biweekly') {
                    $isOdd = $cursor->weekOfYear % 2 === 1;
                    $wantOdd = ($details['week_type'] ?? 'odd') === 'odd';
                    if ($isOdd === $wantOdd) {
                        $dates[] = $cursor->copy();
                    }
                } else {
                    $dates[] = $cursor->copy();
                }
            }
            $cursor->addDay();
        }

        return $dates;
    }
}
