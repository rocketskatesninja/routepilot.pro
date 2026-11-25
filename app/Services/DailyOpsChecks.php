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

        $billing = Tenant::query()->find($tenantId)?->customer_billing !== false;

        $n = fn (int $count, string $one, string $many): string => $count.' '.($count === 1 ? $one : $many);
        $yesterday = Tenant::localToday()->subDay()->toDateString();

        // What the office hears about each morning: [kind, title, how many, the sentence, where to look].
        // The counts are OpsSignals', the same ones the dashboard's Advisories read.
        $checks = [
            ['unassigned_pools', 'Unassigned pools', $count = OpsSignals::unassignedPools()->count(),
                $n($count, 'pool has', 'pools have').' an active plan but no assigned agent.', '/schedule'],
            ['no_routes_tomorrow', 'No routes tomorrow', OpsSignals::nothingTomorrow() ? 1 : 0,
                'No stops are scheduled for tomorrow. Open the schedule to materialize routes.', '/schedule'],
            ['overdue_balances', 'Overdue balances', $count = $billing ? OpsSignals::overdueCustomers()->count() : 0,
                $n($count, 'customer has a bill', 'customers have bills').' past due.', '/balances'],
            ['stale_chemistry', 'Stale chemistry', $count = OpsSignals::untestedPools()->count(),
                $n($count, "pool hasn't", "pools haven't").' been tested in over 2 weeks.', '/pools'],
            ['idle_agents', 'Idle agents', $count = OpsSignals::idleTechs($tenantId)->count(),
                $count.' active '.($count === 1 ? 'agent has' : 'agents have').' no stops scheduled today.', '/schedule'],
            // MarkMissedStops marks them just before these checks run.
            ['missed_stops', 'Missed stops', $count = OpsSignals::missedYesterday()->count(),
                $n($count, 'stop', 'stops').' from yesterday '.($count === 1 ? 'was' : 'were').' never finished or skipped.', '/schedule?date='.$yesterday],
        ];

        $alerts = 0;
        foreach ($checks as [$kind, $title, $count, $body, $url]) {
            if ($count > 0) {
                Notification::send($admins, new OpsAlert($kind, $title, $body, $url));
                $alerts++;
            }
        }

        // Customer-facing reminders (one per affected homeowner, not per tenant).
        $alerts += $this->serviceTomorrow($tenantId);
        $alerts += $billing ? $this->overdueCustomerBalances() : 0;

        return $alerts;
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
}
