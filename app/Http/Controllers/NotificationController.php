<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateNotificationTypesRequest;
use App\Notifications\InAppNotification;
use App\Support\NotificationTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * In-app notification center. Each user sees only their own notifications,
 * and picks which types they get.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return Inertia::render('notifications/Index', [
            'notifications' => $user->notifications()->latest()->limit(40)->get()->map(InAppNotification::row(...))->all(),
            'types' => NotificationTypes::for($user),
            'preferences' => NotificationTypes::preferences($user),
            'pushKey' => config('services.webpush.public_key') ?: null,
        ]);
    }

    public function updateTypes(UpdateNotificationTypesRequest $request): RedirectResponse
    {
        NotificationTypes::set($request->user(), $request->changes());

        return back()->with('success', 'Saved.');
    }

    /**
     * Opening one, from anywhere (the list, a pop-up, the dashboard, a tap on a
     * phone): mark it read and go where it points, or back to the list.
     */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()?->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = $notification->data['url'] ?? null;

        // Only ever a path on this site.
        return redirect(is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : '/notifications');
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
