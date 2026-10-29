<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\ServiceReminderMail;
use App\Models\Customer;
use App\Models\RouteStop;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BalanceReminder;
use App\Notifications\OpsAlert;
use App\Notifications\ServiceReminder;
use App\Support\EmailPreferences;
use App\Support\OpsSignals;
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
        $admins = User::adminsOf($tenantId);

        if ($admins->isEmpty()) {
            return 0;
        }

        $alerts = 0;
        $alerts += $this->unassignedPools($admins) ? 1 : 0;
        $alerts += $this->noRoutesTomorrow($admins) ? 1 : 0;
        $alerts += $this->overdueBalances($admins) ? 1 : 0;
        $alerts += $this->staleChemistry($admins) ? 1 : 0;
        $alerts += $this->idleAgents($admins) ? 1 : 0;
        $alerts += $this->missedYesterday($admins) ? 1 : 0;

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
        $count = OpsSignals::unassignedPools()->count();

        if ($count === 0) {
            return false;
        }

        return $this->alert($admins, 'unassigned_pools', 'Unassigned pools',
            $count.' '.($count === 1 ? 'pool has' : 'pools have').' an active plan but no assigned agent.',
            '/schedule');
    }

    /**
     * Nothing scheduled for tomorrow despite having active plans — likely an oversight.
     *
     * @param  Collection<int, User>  $admins
     */
    private function noRoutesTomorrow(Collection $admins): bool
    {
        if (! OpsSignals::nothingTomorrow()) {
            return false;
        }

        return $this->alert($admins, 'no_routes_tomorrow', 'No routes tomorrow',
            'No stops are scheduled for tomorrow. Open the schedule to materialize routes.',
            '/schedule');
    }

    /**
     * Customers with a bill past its due date.
     *
     * @param  Collection<int, User>  $admins
     */
    private function overdueBalances(Collection $admins): bool
    {
        $count = OpsSignals::overdueCustomers()->count();
        if ($count === 0) {
            return false;
        }

        return $this->alert($admins, 'overdue_balances', 'Overdue balances',
            $count.' '.($count === 1 ? 'customer has a bill' : 'customers have bills').' past due.',
            '/balances');
    }

    /**
     * Active-plan pools with no chemistry reading in the last 14 days.
     *
     * @param  Collection<int, User>  $admins
     */
    private function staleChemistry(Collection $admins): bool
    {
        $count = OpsSignals::untestedPools()->count();
        if ($count === 0) {
            return false;
        }

        return $this->alert($admins, 'stale_chemistry', 'Stale chemistry',
            $count.' '.($count === 1 ? "pool hasn't" : "pools haven't").' been tested in over 2 weeks.',
            '/pools');
    }

    /**
     * Active agents with no route today — capacity sitting idle.
     *
     * @param  Collection<int, User>  $admins
     */
    private function idleAgents(Collection $admins): bool
    {
        $count = OpsSignals::idleTechs((int) $admins->first()?->getAttribute('tenant_id'))->count();
        if ($count === 0) {
            return false;
        }

        return $this->alert($admins, 'idle_agents', 'Idle agents',
            $count.' active '.($count === 1 ? 'agent has' : 'agents have').' no stops scheduled today.',
            '/schedule');
    }

    /**
     * Stops from yesterday that nobody finished or skipped (MarkMissedStops marks
     * them just before these checks run).
     *
     * @param  Collection<int, User>  $admins
     */
    private function missedYesterday(Collection $admins): bool
    {
        $yesterday = Tenant::localToday()->subDay();
        $count = OpsSignals::missedYesterday()->count();

        if ($count === 0) {
            return false;
        }

        return $this->alert($admins, 'missed_stops', 'Missed stops',
            $count.' '.($count === 1 ? 'stop' : 'stops').' from yesterday '.($count === 1 ? 'was' : 'were').' never finished or skipped.',
            '/schedule?date='.$yesterday->toDateString());
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
            ->whereHas('route', fn ($q) => $q->whereDate('scheduled_date', Tenant::localToday()->addDay()))
            ->with(['pool.customer.user', 'route.agent'])
            ->get();

        if ($stops->isEmpty()) {
            return 0;
        }

        $tenant = Tenant::query()->find($tenantId);
        $company = (string) ($tenant->name ?? '');
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
            $optedOut = ! EmailPreferences::wants($customer, 'reminders');
            if (is_string($email) && $email !== '' && ! $optedOut) {
                $date = $stop->route?->scheduled_date;
                Mail::to($email)->queue(new ServiceReminderMail(
                    customerName: $customer->first_name ?: $customer->displayName(),
                    company: $company,
                    poolName: (string) $pool->name,
                    date: $date?->format('l, F j') ?? 'tomorrow',
                    agentName: $stop->route?->agent?->firstName(),
                    tenant: $tenant,
                    customerId: $customer->id,
                ));
            }
        }

        return $sent;
    }

    /** Remind portal customers who have a bill past its due date — weekly, not every morning. */
    private function overdueCustomerBalances(): int
    {
        $customers = Customer::query()
            ->whereHas('invoices', fn ($q) => $q->where('status', 'overdue'))
            ->whereNotNull('user_id')
            ->whereDoesntHave('user.notifications', fn ($q) => $q->where('type', BalanceReminder::class)->where('created_at', '>=', now()->subDays(7)))
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
    private function alert(Collection $admins, string $kind, string $title, string $body, string $url): bool
    {
        Notification::send($admins, new OpsAlert($kind, $title, $body, $url));

        return true;
    }
}
