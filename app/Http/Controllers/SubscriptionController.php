<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateSubscription;
use App\Actions\DeleteSubscription;
use App\Actions\UpdateSubscription;
use App\Http\Requests\SubscriptionRequest;
use App\Models\ServiceSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Service plans, managed from a pool's detail pane.
 * {subscription} binds through the global TenantScope → foreign id 404s.
 */
class SubscriptionController extends Controller
{
    public function store(SubscriptionRequest $request, CreateSubscription $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'Service plan added.');
    }

    public function update(SubscriptionRequest $request, ServiceSubscription $subscription, UpdateSubscription $action): RedirectResponse
    {
        $action->handle($subscription, $request->validated());

        return back()->with('success', 'Service plan updated.');
    }

    public function destroy(Request $request, ServiceSubscription $subscription, DeleteSubscription $action): RedirectResponse
    {
        $this->authorizeAdmin($request);
        abort_if($request->user() === null, 403);

        if (! $action->handle($subscription, $request->user())) {
            return back()->with('error', 'This plan has completed visits, so it can\'t be deleted. End it instead (set its status to Ended) — its history stays.');
        }

        return back()->with('success', 'Service plan removed.');
    }
}
