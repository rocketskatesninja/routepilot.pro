<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * The in-app notifications a user can turn off, one switch per type, from the
 * notifications page. Each notification's via() asks wantsNotification() with
 * its type (OpsAlert uses its kind); a type with no saved choice is on.
 */
final class NotificationTypes
{
    /** type => [name, what it is, who gets it] ('agent_plus' = agents with Agent+) */
    public const TYPES = [
        'lead_submitted' => ['New leads', 'Someone fills in a form or chats on your website.', ['tenant_admin']],
        'service_request' => ['Service requests', 'A customer asks for something from their portal.', ['tenant_admin']],
        'work_order_raised' => ['New work orders', 'Someone on the team raises a work order.', ['tenant_admin', 'agent_plus']],
        'work_order_assigned' => ['Work orders for you', 'Someone assigns a work order to you.', ['tenant_admin', 'agent']],
        'autopay_declined' => ['Declined autopay', 'A customer’s card is declined.', ['tenant_admin']],
        'missed_stops' => ['Missed stops', 'Morning check: yesterday’s stops nobody finished or skipped.', ['tenant_admin']],
        'no_routes_tomorrow' => ['No routes tomorrow', 'Morning check: nothing is scheduled for tomorrow.', ['tenant_admin']],
        'unassigned_pools' => ['Unassigned pools', 'Morning check: pools with a plan but no agent.', ['tenant_admin']],
        'idle_agents' => ['Idle agents', 'Morning check: agents with no stops today.', ['tenant_admin']],
        'stale_chemistry' => ['Stale chemistry', 'Morning check: pools not tested in over 2 weeks.', ['tenant_admin']],
        'overdue_balances' => ['Overdue balances', 'Morning check: customers with bills past due.', ['tenant_admin']],
        'mail_failed' => ['Your mail server', 'Your mail server couldn’t send, so the email went out from RoutePilot.', ['tenant_admin']],
        'visit_completed' => ['Visit reports', 'After each visit: what was done and your water test.', ['customer']],
        'service_reminder' => ['Visit reminders', 'The day before someone comes.', ['customer']],
        'request_answered' => ['Answers to your requests', 'When we reply to something you asked for.', ['customer']],
        'balance_reminder' => ['Balance reminders', 'Once a week while a bill is past due.', ['customer']],
    ];

    /** @return array<string, array{string, string}> type => [name, what it is], only the types this user gets */
    public static function for(User $user): array
    {
        $types = [];
        foreach (self::TYPES as $type => [$name, $about, $who]) {
            if (in_array($user->role, $who, true) || (in_array('agent_plus', $who, true) && $user->isAgentPlus())) {
                $types[$type] = [$name, $about];
            }
        }

        return $types;
    }

    /** @return array<string, bool> type => on */
    public static function preferences(User $user): array
    {
        $saved = $user->notificationPreferences()->pluck('in_app', 'category');
        $on = [];
        foreach (array_keys(self::for($user)) as $type) {
            $on[$type] = (bool) ($saved[$type] ?? true);
        }

        return $on;
    }

    /** @param  array<string, bool>  $changes  type => on */
    public static function set(User $user, array $changes): void
    {
        foreach (array_intersect_key($changes, self::for($user)) as $type => $on) {
            $user->notificationPreferences()->updateOrCreate(['category' => $type], ['in_app' => (bool) $on]);
        }
    }
}
