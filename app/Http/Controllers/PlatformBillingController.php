<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\BillingMeter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super-admin platform-billing overview — MRR, trial pipeline, and per-tenant
 * plan/usage/status across every account. Read-only: the tenants self-serve
 * their own subscriptions via Stripe (see BillingController). Trust boundary is
 * the super-admin guard; nothing here is tenant-scoped.
 */
class PlatformBillingController extends Controller
{
    public function index(Request $request, BillingMeter $meter): Response
    {
        $this->authorizeSuper($request);

        // Eager-load subscriptions so billingState() doesn't re-query per tenant.
        $tenants = Tenant::query()->with('subscriptions')->orderBy('name')->get();
        $usage = $meter->forMany($tenants->pluck('id')->all());

        $rows = $tenants->map(function (Tenant $tenant) use ($usage): array {
            $state = $tenant->billingState();
            $estimated = (float) ($usage[$tenant->id]['estimated_total'] ?? 0.0);

            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $state['status'],
                'subscribed' => $state['subscribed'],
                'trial_ends_at' => $state['trial_ends_at'],
                'pools' => (int) ($usage[$tenant->id]['pools']['used'] ?? 0),
                'agents' => (int) ($usage[$tenant->id]['agents']['used'] ?? 0),
                'estimated' => $estimated,
            ];
        })->values();

        return Inertia::render('admin/Billing', [
            'metrics' => [
                // MRR = realized recurring revenue from subscribed tenants (active
                // or past-due — they still owe). Trial pipeline is not yet revenue.
                'mrr' => round((float) $rows->where('subscribed', true)->sum('estimated'), 2),
                'at_risk' => round((float) $rows->where('status', 'past_due')->sum('estimated'), 2),
                'trial_pipeline' => round((float) $rows->where('status', 'trialing')->sum('estimated'), 2),
                'tenants' => $rows->count(),
                'active' => $rows->where('status', 'active')->count(),
                'trialing' => $rows->where('status', 'trialing')->count(),
                'past_due' => $rows->where('status', 'past_due')->count(),
                'expired' => $rows->where('status', 'expired')->count(),
            ],
            'tenants' => $rows,
            'plan' => [
                'base_price' => round((float) config('billing.base_price'), 2),
                'included_pools' => (int) config('billing.included_pools'),
                'included_agents' => (int) config('billing.included_agents'),
            ],
        ]);
    }
}
