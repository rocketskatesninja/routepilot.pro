<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Soft-lock the back office when a tenant's free trial has lapsed without an
 * active subscription (see Tenant::billingLocked). Super-admins are never
 * locked; the tenant_admin is funnelled to the billing screen to subscribe;
 * everyone else lands on a friendly "account paused" page. Billing, logout and
 * the paused page itself stay reachable so the lock is escapable, not a dead end.
 */
class EnsureBillingActive
{
    /** Routes that must stay reachable while locked, so the tenant can recover or leave. */
    private const ALLOWED = ['billing.show', 'billing.checkout', 'billing.portal', 'account.paused', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Unauthenticated requests and platform super-admins are never billing-locked.
        if ($user === null || $user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = app()->has('tenant') ? app('tenant') : null;
        if (! $tenant instanceof Tenant || ! $tenant->billingLocked()) {
            return $next($request);
        }

        if ($request->routeIs(self::ALLOWED)) {
            return $next($request);
        }

        // The account owner can fix it; everyone else just sees the paused page.
        return $user->role === 'tenant_admin'
            ? redirect()->route('billing.show')
            : redirect()->route('account.paused');
    }
}
