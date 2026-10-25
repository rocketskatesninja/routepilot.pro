<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * A proactive operational alert to tenant admins (unassigned pools, no routes
 * tomorrow, overdue balances, stale chemistry, idle agents, missed stops) —
 * surfaced in the in-app notification bell. Built by DailyOpsChecks; each kind
 * is its own notification type the admin can turn off.
 */
class OpsAlert extends InAppNotification
{
    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public string $url,
    ) {}

    public function type(): string
    {
        return $this->kind;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'kind' => $this->kind,
        ];
    }
}
