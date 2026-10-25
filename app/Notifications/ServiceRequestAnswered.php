<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app notification to a customer when the company answers their request
 * (scheduled, pause set, declined, …); it opens their requests page.
 */
class ServiceRequestAnswered extends Notification
{
    use Queueable;

    public function __construct(public string $title, public string $body) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && ! $notifiable->wantsNotification('request_answered') ? [] : ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => '/requests'];
    }
}
