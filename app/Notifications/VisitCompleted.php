<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ServiceVisit;

/**
 * In-app notification to a homeowner when their pool has been serviced.
 */
class VisitCompleted extends InAppNotification
{
    public function __construct(public ServiceVisit $visit) {}

    public function type(): string
    {
        return 'visit_completed';
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
