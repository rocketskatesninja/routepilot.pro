<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A proactive operational alert to tenant admins (unassigned pools, no routes
 * tomorrow, overdue balances, stale chemistry, idle agents) — surfaced in the
 * in-app notification bell. Built by DailyOpsChecks; respects the admin's `ops`
 * notification preference.
 */
class OpsAlert extends Notification
{
    use Queueable;

    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public string $url,
        public string $icon,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && ! $notifiable->wantsNotification('ops') ? [] : ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'icon' => $this->icon,
            'kind' => $this->kind,
        ];
    }
}
