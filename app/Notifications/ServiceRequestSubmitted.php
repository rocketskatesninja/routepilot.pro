<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ServiceRequest;

/**
 * In-app notification to tenant admins when a customer sends a request; it
 * opens that request in the inbox.
 */
class ServiceRequestSubmitted extends InAppNotification
{
    public function __construct(public ServiceRequest $serviceRequest) {}

    public function type(): string
    {
        return 'service_request';
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
