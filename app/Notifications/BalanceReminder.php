<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app reminder to a homeowner who has a bill past its due date — at most
 * once a week. Built by DailyOpsChecks; respects the customer's
 * `balance_reminder` notification preference.
 */
class BalanceReminder extends Notification
{
    use Queueable;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && ! $notifiable->wantsNotification('balance_reminder') ? [] : ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Balance overdue',
            'body' => 'You have a bill past its due date. Please arrange payment.',
            'url' => '/balance',
            'icon' => 'Banknote',
        ];
    }
}
