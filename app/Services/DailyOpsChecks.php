<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\ServiceReminderMail;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BalanceReminder;
use App\Notifications\OpsAlert;
use App\Notifications\ServiceReminder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Proactive operational checks for one tenant — the un-reactive half of the
 * notification system. Run nightly by the DailyOpsAlerts command (tenant_id is
 * already bound, so the global TenantScope handles isolation). Each check is a
 * single aggregate query; a non-zero result alerts the tenant's admins via the
 * in-app bell (OpsAlert, preference-gated). A check that finds nothing is silent.
 *
 * @return int the number of distinct alerts raised (for the command's summary)
 */
class DailyOpsChecks
{
    public function run(int $tenantId): int
    {
        $admins = User::query()
            ->where('tenant_id', $tenantId)
            ->where('role', 'tenant_admin')
            ->where('is_active', true)
            ->get();

        if ($admins->isEmpty()) {
            return 0;
        }

        $alerts = 0;
        $alerts += $this->unassignedPools($admins) ? 1 : 0;
        $alerts += $this->noRoutesTomorrow($admins) ? 1 : 0;
        $alerts += $this->overdueBalances($admins) ? 1 : 0;
        $alerts += $this->staleChemistry($admins) ? 1 : 0;
        $alerts += $this->idleAgents($admins) ? 1 : 0;

        // Customer-facing reminders (one per affected homeowner, not per tenant).
        $alerts += $this->serviceTomorrow($tenantId);
        $alerts += $this->overdueCustomerBalances();

        return $alerts;
    }

    /**
     * Active subscriptions with no assigned agent — work that won't get routed.
     *
     * @param  Collection<int, User>  $admins
     */
    private function unassignedPools(Collection $admins): bool
    {
        $count = ServiceSubscription::query()
            ->where('status', 'active')
            ->whereNull('assigned_agent_id')
            ->distinct()
            ->count('pool_id');

        if ($count === 0) {
            return false;
        }

        return $this->alert($admins, 'unassigned_pools', 'Unassigned pools',
            $count.' '.($count === 1 ? 'pool has' : 'pools have').' an active plan but no assigned agent.',
            '/schedule', 'Waves');
    }

    /**
     * Nothing scheduled for tomorrow despite having active plans — likely an oversight.
     *
     * @param  Collection<int, User>  $admins
     */
    private function noRoutesTomorrow(Collection $admins): bool
    {
        $hasStops = RouteStop::query()
            ->whereHas('route', fn ($q) => $q->whereDate('scheduled_date', today()->addDay()))
            ->exists();

        if ($hasStops || ! ServiceSubscription::query()->where('status', 'active')->exists()) {
            return false;
        }

        return $this->alert($admins, 'no_routes_tomorrow', 'No routes tomorrow',
            'No stops are scheduled for tomorrow. Open the schedule to materialize routes.',
            '/schedule', 'CalendarDays');
    }

    /**
     * Customers carrying completed-but-unpaid visits older than 30 days.
     *
     * @param  Collection<int, User>  $admins
     */
    private function overdueBalances(Collection $admins): bool
    {
        $poolIds = ServiceVisit::query()
            ->where('status', 'completed')
            ->whereNull('paid_at')
            ->where('visited_at', '<', now()->subDays(30))
            ->distinct()
            ->pluck('pool_id');

        $count = Pool::query()->whereIn('id', $poolIds)->whereNotNull('customer_id')->distinct()->count('customer_id');
        if ($count === 0) {
            return false;
        }

        return $this->alert($admins, 'overdue_balances', 'Overdue balances',
            $count.' '.($count === 1 ? 'customer has' : 'customers have').' unpaid visits over 30 days old.',
            '/balances', 'Banknote');
    }

    /**
     * Active-plan pools with no chemistry reading in the last 14 days.
     *
     * @param  Collection<int, User>  $admins
     */
    private function staleChemistry(Collection $admins): bool
    {
        $activePoolIds = Pool::query()
            ->whereHas('subscriptions', fn ($q) => $q->where('status', 'active'))
            ->pluck('id');

        if ($activePoolIds->isEmpty()) {
            return false;
        }

        $testedPoolIds = ServiceVisit::query()
            ->whereIn('pool_id', $activePoolIds)
            ->whereHas('chemicalReading', fn ($q) => $q->where('created_at', '>=', now()->subDays(14)))
            ->distinct()
            ->pluck('pool_id');

        $count = $activePoolIds->diff($testedPoolIds)->count();
        if ($count === 0) {
            return false;
        }

        return $this->alert($admins, 'stale_chemistry', 'Stale chemistry',
            $count.' '.($count === 1 ? "pool hasn't" : "pools haven't").' been tested in over 2 weeks.',
            '/pools', 'FlaskConical');
    }

    /**
     * Active agents with no route today — capacity sitting idle.
     *
     * @param  Collection<int, User>  $admins
     */
    private function idleAgents(Collection $admins): bool
    {
        $activeAgentIds = User::query()
            ->where('tenant_id', $admins->first()?->getAttribute('tenant_id'))
            ->where('role', 'agent')
            ->where('is_active', true)
            ->pluck('id');

        if ($activeAgentIds->isEmpty()) {
            return false;
        }

        $busyAgentIds = Route::query()->whereDate('scheduled_date', today())->pluck('agent_id')->unique();
        $count = $activeAgentIds->diff($busyAgentIds)->count();
        if ($count === 0) {
            return false;
        }

        return $this->alert($admins, 'idle_agents', 'Idle agents',
            $count.' active '.($count === 1 ? 'agent has' : 'agents have').' no stops scheduled today.',
            '/schedule', 'Users');
    }

    /**
     * Remind homeowners whose pool is scheduled for service tomorrow — in-app
     * (portal users) plus a branded email (skipped for opt-out customers and
     * those who turned off the `service` email channel).
     */
    private function serviceTomorrow(int $tenantId): int
    {
        $stops = RouteStop::query()
            ->where('status', 'pending')
            ->whereHas('route', fn ($q) => $q->whereDate('scheduled_date', today()->addDay()))
            ->with(['pool.customer.user', 'route.agent'])
            ->get();

        if ($stops->isEmpty()) {
            return 0;
        }

        $company = (string) (Tenant::query()->whereKey($tenantId)->value('name') ?? '');
        $sent = 0;

        foreach ($stops as $stop) {
            $pool = $stop->pool;
            $customer = $pool?->customer;
            if ($pool === null || $customer === null) {
                continue;
            }

            $user = $customer->user;
            if ($user !== null) {
                $user->notify(new ServiceReminder((string) $pool->name));
                $sent++;
            }

            $email = $customer->email;
            $optedOut = (bool) $customer->getAttribute('email_opt_out');
            $emailAllowed = $user === null || $user->wantsNotification('service', 'email');
            if (is_string($email) && $email !== '' && ! $optedOut && $emailAllowed) {
                $date = $stop->route?->scheduled_date;
                Mail::to($email)->queue(new ServiceReminderMail(
                    customerName: $customer->displayName(),
                    company: $company,
                    poolName: (string) $pool->name,
                    date: $date?->format('l, F j') ?? 'tomorrow',
                    agentName: $stop->route?->agent?->displayName(),
                ));
            }
        }

        return $sent;
    }

    /** Remind portal customers carrying 30+ day unpaid visits. */
    private function overdueCustomerBalances(): int
    {
        $poolIds = ServiceVisit::query()
            ->where('status', 'completed')
            ->whereNull('paid_at')
            ->where('visited_at', '<', now()->subDays(30))
            ->distinct()
            ->pluck('pool_id');

        if ($poolIds->isEmpty()) {
            return 0;
        }

        $customers = Customer::query()
            ->whereHas('pools', fn ($q) => $q->whereIn('id', $poolIds))
            ->whereNotNull('user_id')
            ->with('user')
            ->get();

        $sent = 0;
        foreach ($customers as $customer) {
            if ($customer->user !== null) {
                $customer->user->notify(new BalanceReminder);
                $sent++;
            }
        }

        return $sent;
    }

    /** @param  Collection<int, User>  $admins */
    private function alert(Collection $admins, string $kind, string $title, string $body, string $url, string $icon): bool
    {
        Notification::send($admins, new OpsAlert($kind, $title, $body, $url, $icon));

        return true;
    }
}
