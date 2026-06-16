<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app reminder to a homeowner who has service visits unpaid for 30+ days.
 * Built by DailyOpsChecks; respects the customer's `billing` notification
 * preference.
 */
class BalanceReminder extends Notification
{
    use Queueable;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && ! $notifiable->wantsNotification('billing') ? [] : ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Balance overdue',
            'body' => 'You have service visits unpaid for over 30 days. Please arrange payment.',
            'url' => '/balance',
            'icon' => 'Banknote',
        ];
    }
}
