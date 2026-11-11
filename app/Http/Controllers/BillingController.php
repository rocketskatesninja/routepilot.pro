<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\BillingMeter;
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
                'price_per_pool' => (float) config('billing.price_per_pool'),
            ],
        ]);
    }

    /** Start (or resume) a Stripe Checkout: the base price, plus the pools over the allowance. */
    public function checkout(Request $request, BillingMeter $meter): Responsable|RedirectResponse
    {
        $tenant = $this->tenant($request);

        if (! $this->configured()) {
            return back()->with('error', 'Online billing isn’t available yet — hang tight.');
        }
        if ($tenant->subscribed()) {
            return redirect()->route('billing.show');
        }

        // Pools over the allowance are on the first bill too; app:sync-pool-billing keeps them in step after.
        $base = (string) config('billing.prices.base');
        $pool = (string) config('billing.prices.pool');
        $extra = $pool !== '' ? (int) $meter->for($tenant)['pools']['over'] : 0;
        $builder = $tenant->newSubscription('default', $extra > 0 ? [$base, $pool] : $base);
        if ($extra > 0) {
            $builder->quantity($extra, $pool);
        }

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
