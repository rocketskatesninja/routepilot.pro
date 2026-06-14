<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateSubscription;
use App\Actions\UpdateSubscription;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Requests\UpdateSubscriptionRequest;
use App\Models\ServiceSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Subscription (service-plan) mutations, managed from a pool's drawer.
 * {subscription} binds through the global TenantScope → foreign id 404s.
 */
class SubscriptionController extends Controller
{
    public function store(StoreSubscriptionRequest $request, CreateSubscription $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'Service plan added.');
    }

    public function update(UpdateSubscriptionRequest $request, ServiceSubscription $subscription, UpdateSubscription $action): RedirectResponse
    {
        $action->handle($subscription, $request->validated());

        return back()->with('success', 'Service plan updated.');
    }

    public function destroy(Request $request, ServiceSubscription $subscription): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $subscription->delete();

        return back()->with('success', 'Service plan removed.');
    }
}
