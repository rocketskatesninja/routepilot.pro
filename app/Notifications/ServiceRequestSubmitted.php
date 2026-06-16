<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app notification to tenant admins when a homeowner submits a request.
 */
class ServiceRequestSubmitted extends Notification
{
    use Queueable;

    public function __construct(public ServiceRequest $serviceRequest) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && ! $notifiable->wantsNotification('requests') ? [] : ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New '.($this->serviceRequest->type === 'hold' ? 'vacation hold' : 'service').' request',
            'body' => $this->serviceRequest->message,
            'url' => '/dashboard',
        ];
    }
}
