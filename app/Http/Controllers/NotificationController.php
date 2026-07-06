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
                'on' => $n->created_at?->diffForHumans(),
            ])->all();

        return Inertia::render('notifications/Index', ['notifications' => $notifications]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $request->user()?->notifications()->where('id', $id)->update(['read_at' => now()]);

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
