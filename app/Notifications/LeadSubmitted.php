<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app notification to tenant admins when a new public-site lead arrives.
 */
class LeadSubmitted extends Notification
{
    use Queueable;

    public function __construct(public Lead $lead) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New lead: '.$this->lead->name,
            'body' => $this->lead->source.($this->lead->email !== null ? ' · '.$this->lead->email : ''),
            'url' => '/leads',
        ];
    }
}
