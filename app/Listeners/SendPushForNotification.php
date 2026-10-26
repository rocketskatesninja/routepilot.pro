<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\SendWebPush;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Storage;

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
            'url' => '/notifications/'.$saved->id.'/open', // marks it read on the way
            'tag' => (string) $saved->id,
            'icon' => $this->logo($user),
        ]);
    }

    /** The user's company logo shows on the notification (else the phone shows RoutePilot's icon). */
    private function logo(User $user): ?string
    {
        $path = $user->tenant?->logo_path;

        return is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
    }
}
