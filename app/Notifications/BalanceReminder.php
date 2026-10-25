<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * In-app reminder to a homeowner who has a bill past its due date — at most
 * once a week. Built by DailyOpsChecks; respects the customer's
 * `balance_reminder` notification preference.
 */
class BalanceReminder extends InAppNotification
{
    public function type(): string
    {
        return 'balance_reminder';
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Balance overdue',
            'body' => 'You have a bill past its due date. Please arrange payment.',
            'url' => '/balance',
        ];
    }
}
