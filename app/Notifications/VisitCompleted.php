<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ServiceVisit;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app notification to a homeowner when their pool has been serviced.
 */
class VisitCompleted extends Notification
{
    use Queueable;

    public function __construct(public ServiceVisit $visit) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && ! $notifiable->wantsNotification('service') ? [] : ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Service completed',
            'body' => $this->visit->pool->name.' was serviced.',
            'url' => '/history',
        ];
    }
}
