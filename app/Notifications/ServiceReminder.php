<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * In-app reminder to a homeowner that their pool is scheduled for service
 * tomorrow. Built by DailyOpsChecks; respects the customer's
 * `service_reminder` notification preference. (The branded email reminder is
 * sent separately.)
 */
class ServiceReminder extends InAppNotification
{
    public function __construct(public string $poolName) {}

    public function type(): string
    {
        return 'service_reminder';
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Service tomorrow',
            'body' => $this->poolName.' is scheduled for service tomorrow.',
            'url' => '/dashboard',
        ];
    }
}
