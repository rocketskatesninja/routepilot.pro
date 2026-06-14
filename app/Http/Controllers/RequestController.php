<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateServiceRequest;
use App\Http\Requests\StoreServiceRequestRequest;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\ServiceRequestSubmitted;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/**
 * Service requests — created by homeowners (portal), resolved by the tenant.
 * ServiceRequest is tenant-scoped, so the {serviceRequest} binding can only
 * resolve a request belonging to the admin's own tenant.
 */
class RequestController extends Controller
{
    public function store(StoreServiceRequestRequest $request, CreateServiceRequest $action): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $customer = Customer::query()->where('user_id', $user->id)->first();
        abort_if($customer === null, 404);

        $serviceRequest = $action->handle($request->validated(), $customer);

        $admins = User::query()
            ->where('tenant_id', $customer->getAttribute('tenant_id'))
            ->where('role', 'tenant_admin')->where('is_active', true)
            ->get();
        Notification::send($admins, new ServiceRequestSubmitted($serviceRequest));

        return back()->with('success', 'Request sent — your service company will follow up.');
    }

    public function resolve(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user), 403);

        $serviceRequest->forceFill(['status' => 'resolved', 'resolved_by' => $user->id, 'resolved_at' => now()])->save();

        return back()->with('success', 'Request marked resolved.');
    }
}
