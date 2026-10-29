<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateNotificationTypesRequest;
use App\Notifications\AutopayDeclined;
use App\Notifications\BalanceReminder;
use App\Notifications\InAppNotification;
use App\Notifications\LeadSubmitted;
use App\Notifications\OpsAlert;
use App\Notifications\ServiceReminder;
use App\Notifications\ServiceRequestAnswered;
use App\Notifications\ServiceRequestSubmitted;
use App\Notifications\VisitCompleted;
use App\Notifications\WorkOrderAssigned;
use App\Notifications\WorkOrderRaised;
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
    /** What the page can filter by: a name, and the notification classes that are that kind. */
    private const KINDS = [
        'requests' => ['Requests', [ServiceRequestSubmitted::class, ServiceRequestAnswered::class]],
        'leads' => ['Website leads', [LeadSubmitted::class]],
        'work_orders' => ['Work orders', [WorkOrderRaised::class, WorkOrderAssigned::class]],
        'visits' => ['Visit reports', [VisitCompleted::class]],
        'billing' => ['Billing', [BalanceReminder::class, AutopayDeclined::class]],
        'reminders' => ['Visit reminders', [ServiceReminder::class]],
        'alerts' => ['Alerts', [OpsAlert::class]],
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        // Filtered and paged on the server, so every notification is reachable and the counts are true.
        $unread = $request->string('filter')->toString() === 'unread';
        $kind = $request->string('kind')->toString();
        $kind = array_key_exists($kind, self::KINDS) ? $kind : null;
        $list = $user->notifications()->latest()
            ->when($unread, fn ($q) => $q->whereNull('read_at'))
            ->when($kind !== null, fn ($q) => $q->whereIn('type', self::KINDS[$kind][1]))
            ->paginate(25)->withQueryString()
            ->through(InAppNotification::row(...));

        // Only the kinds this person actually has, to filter by.
        $types = $user->notifications()->distinct()->pluck('type')->all();
        $kinds = [];
        foreach (self::KINDS as $key => [$label, $classes]) {
            if (array_intersect($classes, $types) !== []) {
                $kinds[$key] = $label;
            }
        }

        return Inertia::render('notifications/Index', [
            // One prop with its counts, so the live refresh (app.ts reloads `notifications`) keeps them current.
            'notifications' => [
                'list' => $list,
                'all' => $user->notifications()->count(),
                'unread' => $user->unreadNotifications()->count(),
            ],
            'filter' => $unread ? 'unread' : 'all',
            'kind' => $kind,
            'kinds' => $kinds,
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

    /** Mark several read or unread, or dismiss them — only ever the person's own. */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['string', 'max:64'],
            'action' => ['required', 'in:read,unread,dismiss'],
        ]);
        $mine = $request->user()?->notifications()->whereIn('id', $data['ids']);
        if ($data['action'] === 'dismiss') {
            $mine?->delete();
        } else {
            $mine?->update(['read_at' => $data['action'] === 'read' ? now() : null]);
        }

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
