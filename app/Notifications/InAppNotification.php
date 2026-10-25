<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;

/**
 * An in-app notification: saved for the bell and sent live (pop-up on a
 * desktop; SendPushForNotification also pushes it to the user's phones). Each
 * one is a type from NotificationTypes that its user can turn off.
 */
abstract class InAppNotification extends Notification
{
    use Queueable;

    /** Its NotificationTypes key. */
    abstract public function type(): string;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && ! $notifiable->wantsNotification($this->type()) ? [] : ['database', 'broadcast'];
    }

    /**
     * A saved one as the bell list and the dashboard widget show it. Opening it
     * goes through /notifications/{id}/open, which marks it read on the way.
     *
     * @return array{id: string, title: string, body: string, read: bool, severity: string, icon: string, on: string|null}
     */
    public static function row(DatabaseNotification $n): array
    {
        $class = class_basename((string) $n->type);

        return [
            'id' => (string) $n->id,
            'title' => (string) ($n->data['title'] ?? 'Notification'),
            'body' => (string) ($n->data['body'] ?? ''),
            'read' => $n->read_at !== null,
            'severity' => match ($class) {
                'OpsAlert', 'AutopayDeclined' => 'alert',
                'BalanceReminder', 'ServiceReminder' => 'warning',
                'VisitCompleted' => 'success',
                default => 'info',
            },
            'icon' => match ($class) { // a lucide icon name, resolved on the client
                'OpsAlert' => 'TriangleAlert',
                'AutopayDeclined' => 'CreditCard',
                'BalanceReminder' => 'Banknote',
                'ServiceReminder' => 'CalendarClock',
                'VisitCompleted' => 'CircleCheck',
                'LeadSubmitted' => 'UserPlus',
                'ServiceRequestSubmitted', 'ServiceRequestAnswered' => 'Inbox',
                default => 'Bell',
            },
            'on' => $n->created_at?->diffForHumans(),
        ];
    }
}
