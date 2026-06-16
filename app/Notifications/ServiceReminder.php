<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app reminder to a homeowner that their pool is scheduled for service
 * tomorrow. Built by DailyOpsChecks; respects the customer's `service`
 * notification preference. (The branded email reminder is sent separately.)
 */
class ServiceReminder extends Notification
{
    use Queueable;

    public function __construct(public string $poolName) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && ! $notifiable->wantsNotification('service') ? [] : ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Service tomorrow',
            'body' => $this->poolName.' is scheduled for service tomorrow.',
            'url' => '/dashboard',
            'icon' => 'CalendarDays',
        ];
    }
}
