<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ConvertLead;
use App\Http\Requests\ConvertLeadRequest;
use App\Http\Requests\StoreLeadRequest;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\LeadSubmitted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/**
 * Public-site leads — captured unauthenticated (by tenant), worked from the
 * company's inbox, where a lead is converted into a customer. Lead status is
 * set via controlled update (not mass-assigned).
 */
class LeadController extends Controller
{
    /** Public capture — the {tenant} comes from the landing site's slug. */
    public function store(StoreLeadRequest $request, Tenant $tenant): JsonResponse
    {
        app()->instance('tenant_id', $tenant->id);
        $lead = Lead::create($request->validated());

        $admins = User::query()
            ->where('tenant_id', $tenant->id)->where('role', 'tenant_admin')->where('is_active', true)
            ->get();
        Notification::send($admins, new LeadSubmitted($lead));

        return response()->json(['ok' => true]);
    }

    public function updateStatus(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate(['status' => ['required', 'in:new,contacted,converted,archived']]);
        $lead->forceFill(['status' => $validated['status']])->save();

        return back()->with('success', 'Lead updated.');
    }

    public function convert(ConvertLeadRequest $request, Lead $lead, ConvertLead $action): RedirectResponse
    {
        $result = $action->handle($lead, $request->validated());
        if ($result === null) {
            return back()->with('error', 'This lead is already a customer.');
        }

        // Their website pick is now a request to approve; otherwise open the new customer.
        return $result['request'] !== null
            ? redirect('/inbox?selected='.$result['request']->id)->with('success', 'Customer added — approve their request to schedule it.')
            : redirect('/people?selected='.$result['customer']->id.'&selected_type=customer')->with('success', 'Customer added.');
    }
}
