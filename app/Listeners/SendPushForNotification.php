<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\SendWebPush;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Every in-app notification also goes to the phones its user turned
 * notifications on for. It follows the saved (database) copy, so it obeys the
 * per-type switches: a type they turned off never gets this far.
 */
class SendPushForNotification
{
    public function handle(NotificationSent $event): void
    {
        $user = $event->notifiable;
        $saved = $event->response;
        if ($event->channel !== 'database' || ! $user instanceof User || ! $saved instanceof DatabaseNotification
            || ! $user->pushSubscriptions()->exists()) {
            return;
        }

        SendWebPush::dispatch($user->id, [
            'title' => (string) ($saved->data['title'] ?? 'RoutePilot'),
            'body' => (string) ($saved->data['body'] ?? ''),
            'url' => (string) ($saved->data['url'] ?? '/notifications'),
            'tag' => (string) $saved->id,
        ]);
    }
}
