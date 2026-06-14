<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RegisterTenant;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;

/**
 * Super-admin tenant create/update. Listing + management lives on the super
 * People screen (people/Platform). Super admins carry no tenant context, so the
 * global TenantScope is inert here and these writes legitimately span tenants.
 */
class TenantController extends Controller
{
    public function store(StoreTenantRequest $request, RegisterTenant $register): RedirectResponse
    {
        // RegisterTenant builds the tenant + its first admin atomically; a
        // console-created admin is pre-verified so they can sign in immediately.
        $admin = $register($request->validated());
        $admin->forceFill(['email_verified_at' => now()])->save();

        return back()->with('success', 'Tenant created.');
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update($request->validated());

        return back()->with('success', 'Tenant updated.');
    }
}
