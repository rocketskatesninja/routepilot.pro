<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * In-app notification to a customer when the company answers their request
 * (scheduled, pause set, declined, …); it opens their requests page.
 */
class ServiceRequestAnswered extends InAppNotification
{
    public function __construct(public string $title, public string $body) {}

    public function type(): string
    {
        return 'request_answered';
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => '/requests'];
    }
}
