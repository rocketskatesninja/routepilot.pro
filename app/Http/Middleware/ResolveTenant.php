<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the active tenant and bind it into the container so the global
 * TenantScope (app('tenant_id')) scopes every tenant-owned query.
 *
 * Resolution order (rewritten from the legacy slug-only middleware):
 *  1. STAFF — an authenticated non-super-admin user carries their tenant in
 *     the session; we bind directly from $user->tenant_id (slug-free, no
 *     subdomain redirect dance).
 *  2. PUBLIC — unauthenticated/marketing/portal hosts resolve by matching
 *     the request host to a tenant's custom `primary_domain`, then falling
 *     back to a `slug.routepilot.pro` subdomain.
 *  3. Super admins and the bare main domain pass through with no tenant.
 *
 * User route-model bindings are NOT globally scoped, so we still assert an
 * authenticated user belongs to the resolved tenant (cross-tenant guard).
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 1. Staff: tenant comes from the session-authenticated user.
        if ($user && ! $user->isSuperAdmin() && $user->tenant_id) {
            $tenant = $user->tenant;

            // A suspended/cancelled company is locked out — unless a super-admin
            // is impersonating, so they can still get in to put things right.
            if ($tenant !== null && $tenant->getAttribute('status') !== 'active' && ! $request->session()->has('impersonator_id')) {
                abort(403, 'This company account is suspended. Please contact support.');
            }

            $this->bind($tenant);

            return $next($request);
        }

        // 2. Public: resolve by custom domain, then subdomain slug.
        $tenant = $this->resolveFromHost($request->getHost());

        if ($tenant) {
            // If a user is authenticated on this host, they must belong here.
            if ($user && ! $user->isSuperAdmin() && $user->tenant_id !== $tenant->id) {
                abort(403, 'You do not have access to this company.');
            }
            $this->bind($tenant);
        }

        // 3. Super admins / bare main domain: no tenant context.
        return $next($request);
    }

    /**
     * Match the request host to a tenant by custom domain or subdomain.
     */
    protected function resolveFromHost(string $host): ?Tenant
    {
        $tenant = Tenant::query()->where('primary_domain', $host)->first();
        if ($tenant) {
            return $tenant;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: '';
        if ($appHost !== '' && str_ends_with($host, '.'.$appHost)) {
            $slug = substr($host, 0, -1 * (strlen($appHost) + 1));
            if ($slug !== '' && $slug !== 'www') {
                return Tenant::query()->where('slug', $slug)->first();
            }
        }

        return null;
    }

    protected function bind(Tenant $tenant): void
    {
        app()->instance('tenant', $tenant);
        app()->instance('tenant_id', $tenant->id);
    }
}
