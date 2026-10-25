<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Lead;

/**
 * In-app notification to tenant admins when a new public-site lead arrives.
 */
class LeadSubmitted extends InAppNotification
{
    public function __construct(public Lead $lead) {}

    public function type(): string
    {
        return 'lead_submitted';
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New lead: '.$this->lead->name,
            'body' => $this->lead->source.($this->lead->email !== null ? ' · '.$this->lead->email : ''),
            'url' => '/inbox?tab=leads&selected='.$this->lead->id,
        ];
    }
}
