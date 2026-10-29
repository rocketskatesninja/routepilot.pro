<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The things in a company that need attention, asked one way: the nightly alerts
 * (DailyOpsChecks) and the dashboard's Advisories widget both read them here, so
 * they can't disagree. Tenant-scoped via the bound tenant (User is passed the id).
 */
final class OpsSignals
{
    /**
     * Pools with an active plan but no tech on it: work that won't get routed.
     *
     * @return Collection<int, Pool>
     */
    public static function unassignedPools(): Collection
    {
        return Pool::query()
            ->whereIn('id', ServiceSubscription::query()->where('status', 'active')->whereNull('assigned_agent_id')->select('pool_id'))
            ->orderBy('name')->get(['id', 'name']);
    }

    /** Nothing scheduled for tomorrow despite active plans: likely an oversight. */
    public static function nothingTomorrow(): bool
    {
        return ! RouteStop::query()->whereHas('route', fn ($q) => $q->whereDate('scheduled_date', Tenant::localToday()->addDay()))->exists()
            && ServiceSubscription::query()->where('status', 'active')->exists();
    }

    /**
     * Customers with a bill past its due date (archived ones still owe).
     *
     * @return Collection<int, Customer>
     */
    public static function overdueCustomers(): Collection
    {
        return Customer::withTrashed()
            ->whereHas('invoices', fn ($q) => $q->where('status', 'overdue'))
            ->orderBy('first_name')->get();
    }

    /**
     * Active-plan pools with no water test in the last 14 days.
     *
     * @return Collection<int, Pool>
     */
    public static function untestedPools(): Collection
    {
        return Pool::query()
            ->whereHas('subscriptions', fn ($q) => $q->where('status', 'active'))
            ->whereDoesntHave('visits', fn ($q) => $q->whereHas('chemicalReading', fn ($r) => $r->where('created_at', '>=', now()->subDays(14))))
            ->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Active techs with no route today.
     *
     * @return Collection<int, User>
     */
    public static function idleTechs(int $tenantId): Collection
    {
        return User::query()
            ->where('tenant_id', $tenantId)->where('role', 'agent')->where('is_active', true)
            ->whereNotIn('id', Route::query()->whereDate('scheduled_date', Tenant::localToday())->select('agent_id'))
            ->orderBy('first_name')->get();
    }

    /**
     * Yesterday's stops nobody finished or skipped (MarkMissedStops marks them nightly),
     * optionally one tech's.
     *
     * @return Collection<int, RouteStop>
     */
    public static function missedYesterday(?int $agentId = null): Collection
    {
        return RouteStop::query()
            ->where('status', 'missed')
            ->whereHas('route', fn ($q) => $q->whereDate('scheduled_date', Tenant::localToday()->subDay())
                ->when($agentId !== null, fn ($r) => $r->where('agent_id', $agentId)))
            ->with('pool:id,name')->get();
    }
}
