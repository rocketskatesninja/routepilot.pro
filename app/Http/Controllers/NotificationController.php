<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * In-app notification center. Each user sees only their own notifications.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $notifications = $user->notifications()->latest()->limit(40)->get()
            ->map(fn (DatabaseNotification $n): array => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Notification',
                'body' => $n->data['body'] ?? '',
                'url' => $n->data['url'] ?? null,
                'read' => $n->read_at !== null,
                'severity' => self::severityFor($n->type),
                'icon' => self::iconFor($n->type),
                'on' => $n->created_at?->diffForHumans(),
            ])->all();

        return Inertia::render('notifications/Index', ['notifications' => $notifications]);
    }

    /** Color bucket for a notification, derived from its class so it's consistent. */
    private static function severityFor(?string $type): string
    {
        return match (class_basename((string) $type)) {
            'OpsAlert', 'AutopayDeclined' => 'alert',
            'BalanceReminder', 'ServiceReminder' => 'warning',
            'VisitCompleted' => 'success',
            default => 'info',
        };
    }

    /** Lucide icon name (resolved to a component on the client) for a notification type. */
    private static function iconFor(?string $type): string
    {
        return match (class_basename((string) $type)) {
            'OpsAlert' => 'TriangleAlert',
            'AutopayDeclined' => 'CreditCard',
            'BalanceReminder' => 'Banknote',
            'ServiceReminder' => 'CalendarClock',
            'VisitCompleted' => 'CircleCheck',
            'LeadSubmitted' => 'UserPlus',
            'ServiceRequestSubmitted' => 'Inbox',
            default => 'Bell',
        };
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $request->user()?->notifications()->where('id', $id)->update(['read_at' => now()]);

        return back();
    }

    public function unread(Request $request, string $id): RedirectResponse
    {
        $request->user()?->notifications()->where('id', $id)->update(['read_at' => null]);

        return back();
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->user()?->notifications()->where('id', $id)->delete();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()?->unreadNotifications->markAsRead();

        return back();
    }

    public function clear(Request $request): RedirectResponse
    {
        $request->user()?->notifications()->delete();

        return back()->with('success', 'Notifications cleared.');
    }
}
