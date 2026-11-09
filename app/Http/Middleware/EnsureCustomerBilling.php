<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The balance, invoice and customer-payment screens don't exist for a company
 * that doesn't bill through RoutePilot: 404 when it's switched off. Not to be
 * confused with EnsureBillingActive, the company's own RoutePilot subscription.
 */
class EnsureCustomerBilling
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app()->has('tenant') ? app('tenant') : null;

        abort_if($tenant instanceof Tenant && ! $tenant->customer_billing, 404);

        return $next($request);
    }
}
