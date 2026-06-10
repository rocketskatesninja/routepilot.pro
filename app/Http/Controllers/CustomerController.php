<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCustomer;
use App\Actions\GrantPortalAccess;
use App\Actions\UpdateCustomer;
use App\Http\Requests\GrantPortalRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Customer mutations for the back office. Route-model binding resolves
 * {customer} through the global TenantScope, so a foreign id 404s. Mutations
 * are tenant_admin-only (enforced in the Form Requests / inline).
 */
class CustomerController extends Controller
{
    public function store(StoreCustomerRequest $request, CreateCustomer $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'Customer added.');
    }

    public function update(UpdateCustomerRequest $request, Customer $customer, UpdateCustomer $action): RedirectResponse
    {
        $action->handle($customer, $request->validated());

        return back()->with('success', 'Customer updated.');
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless($request->user()?->role === 'tenant_admin', 403);
        $customer->delete();

        return back()->with('success', 'Customer removed.');
    }

    public function grantPortal(GrantPortalRequest $request, Customer $customer, GrantPortalAccess $action): RedirectResponse
    {
        if ($customer->user_id !== null) {
            return back()->with('error', 'This customer already has portal access.');
        }
        if ($customer->email === null) {
            return back()->with('error', 'Add an email for this customer first.');
        }
        if (User::query()->where('email', $customer->email)->exists()) {
            return back()->with('error', 'That email is already in use by another login.');
        }

        $action->handle($customer, (string) $request->validated()['password']);

        return back()->with('success', 'Portal access granted.');
    }
}
