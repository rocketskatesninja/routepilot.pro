<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ApproveServiceRequest;
use App\Actions\CreateServiceRequest;
use App\Actions\DeclineServiceRequest;
use App\Http\Requests\ApproveServiceRequestRequest;
use App\Http\Requests\DeclineServiceRequestRequest;
use App\Http\Requests\StoreServiceRequestRequest;
use App\Models\Customer;
use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Service requests — sent by customers (portal), answered by the company
 * (inbox). ServiceRequest is tenant-scoped, so the {serviceRequest} binding can
 * only resolve a request belonging to the admin's own company.
 */
class RequestController extends Controller
{
    public function store(StoreServiceRequestRequest $request, CreateServiceRequest $action): RedirectResponse
    {
        $customer = Customer::query()->where('user_id', $request->user()?->id)->first();
        abort_if($customer === null, 404);

        $action->handle($request->validated(), $customer);

        return back()->with('success', 'Request sent — your service company will get back to you here.');
    }

    public function approve(ApproveServiceRequestRequest $request, ServiceRequest $serviceRequest, ApproveServiceRequest $action): RedirectResponse
    {
        abort_if($request->user() === null, 403);

        if (! $action->handle($serviceRequest, $request->validated(), $request->user())) {
            return back()->with('error', 'This request was already answered.');
        }

        return back()->with('success', $serviceRequest->isForService() ? 'Approved — the plan is on the schedule.' : 'Done — the customer has been told.');
    }

    public function decline(DeclineServiceRequestRequest $request, ServiceRequest $serviceRequest, DeclineServiceRequest $action): RedirectResponse
    {
        abort_if($request->user() === null, 403);

        if (! $action->handle($serviceRequest, (string) $request->validated('reply'), $request->user())) {
            return back()->with('error', 'This request was already answered.');
        }

        return back()->with('success', 'Declined — the customer has been told why.');
    }
}
