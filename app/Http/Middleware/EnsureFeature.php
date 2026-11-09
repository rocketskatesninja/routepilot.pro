<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `feature:landing_page`: 404 when the company has that part of RoutePilot
 * switched off. The company is the one in the route (a public page, by slug) or
 * the signed-in one; no company, like a super admin, passes.
 */
class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = $request->route('tenant');
        $tenant = $tenant instanceof Tenant ? $tenant : (app()->has('tenant') ? app('tenant') : null);

        abort_if($tenant instanceof Tenant && ! $tenant->getAttribute($feature), 404);

        return $next($request);
    }
}
