<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tenant platform-billing screen — trial countdown, metered usage, and the
 * Stripe Checkout / billing-portal hand-offs. The signed-in tenant's billing
 * state + usage already arrive via the shared `billing` prop; this controller
 * adds the plan details and drives the subscribe / manage actions.
 *
 * Tenant_admin only (the account owner). Stripe calls are gated behind
 * `configured()` so the screen renders fine before keys/prices are set.
 */
class BillingController extends Controller
{
    public function show(Request $request): Response
    {
        $this->tenant($request); // tenant_admin guard

        return Inertia::render('settings/Billing', [
            'configured' => $this->configured(),
            'plan' => [
                'base_price' => round((float) config('billing.base_price'), 2),
                'included_pools' => (int) config('billing.included_pools'),
                'included_agents' => (int) config('billing.included_agents'),
                'price_per_pool' => (float) config('billing.price_per_pool'),
                'price_per_agent' => (float) config('billing.price_per_agent'),
            ],
        ]);
    }

    /** Start (or resume) a Stripe Checkout for the base subscription. */
    public function checkout(Request $request): Responsable|RedirectResponse
    {
        $tenant = $this->tenant($request);

        if (! $this->configured()) {
            return back()->with('error', 'Online billing isn’t available yet — hang tight.');
        }
        if ($tenant->subscribed()) {
            return redirect()->route('billing.show');
        }

        $builder = $tenant->newSubscription('default', (string) config('billing.prices.base'));

        // Carry any remaining free trial onto the subscription (no charge until it ends).
        if ($tenant->onTrial() && $tenant->trial_ends_at !== null) {
            $builder->trialUntil($tenant->trial_ends_at);
        }

        return $builder->checkout([
            'success_url' => route('billing.show').'?checkout=success',
            'cancel_url' => route('billing.show'),
        ]);
    }

    /** Send the tenant to the Stripe billing portal to manage their subscription. */
    public function portal(Request $request): RedirectResponse
    {
        $tenant = $this->tenant($request);

        if (! $this->configured() || ! $tenant->hasStripeId()) {
            return back()->with('error', 'No active billing account to manage yet.');
        }

        return $tenant->redirectToBillingPortal(route('billing.show'));
    }

    /** Online billing needs a Stripe secret key + a base Price ID. */
    private function configured(): bool
    {
        return filled(config('cashier.secret')) && filled(config('billing.prices.base'));
    }

    private function tenant(Request $request): Tenant
    {
        $user = $request->user();
        abort_unless($user !== null && $user->role === 'tenant_admin', 403);
        $tenant = $user->tenant;
        abort_if($tenant === null, 403);

        return $tenant;
    }
}
