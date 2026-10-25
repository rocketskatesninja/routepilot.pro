<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePushSubscriptionRequest;
use App\Models\PushSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Turning notifications on or off for one phone (web push). The browser makes
 * the subscription; this keeps the server's copy. An endpoint is one device,
 * so subscribing from a phone someone else used moves it to you.
 */
class PushSubscriptionController extends Controller
{
    public function store(StorePushSubscriptionRequest $request): RedirectResponse
    {
        // An impersonator's own phone must never start getting the user's notifications.
        abort_if($request->session()->has('impersonator_id'), 403);
        abort_if(! config('services.webpush.public_key'), 404);

        PushSubscription::query()->firstOrNew(['endpoint' => $request->string('endpoint')->toString()])
            ->forceFill([
                'user_id' => $request->user()?->id,
                'public_key' => $request->string('keys.p256dh')->toString(),
                'auth_token' => $request->string('keys.auth')->toString(),
            ])->save();

        return back()->with('success', 'Notifications are on for this phone.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()?->pushSubscriptions()->where('endpoint', $request->string('endpoint')->toString())->delete();

        return back();
    }
}
