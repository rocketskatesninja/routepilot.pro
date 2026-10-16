<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app notification to tenant admins when a customer sends a request; it
 * opens that request in the inbox.
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
        $request = $this->serviceRequest;

        return [
            'title' => 'New request: '.$request->title(),
            'body' => trim(($request->customer?->displayName() ?? '').($request->message !== null ? ' — '.$request->message : '')),
            'url' => '/inbox?selected='.$request->id,
        ];
    }
}
