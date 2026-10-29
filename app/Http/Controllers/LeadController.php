<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CaptureLead;
use App\Actions\ConvertLead;
use App\Actions\DeleteFromInbox;
use App\Http\Requests\ConvertLeadRequest;
use App\Http\Requests\DeleteInboxItemsRequest;
use App\Http\Requests\StoreLeadRequest;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public-site leads — captured unauthenticated (by tenant), worked from the
 * company's inbox, where a lead is converted into a customer. Lead status is
 * set via controlled update (not mass-assigned).
 */
class LeadController extends Controller
{
    /** Public capture — the {tenant} comes from the landing site's slug. */
    public function store(StoreLeadRequest $request, Tenant $tenant, CaptureLead $capture): JsonResponse
    {
        app()->instance('tenant_id', $tenant->id);

        if ($capture->handle($tenant->id, $request->validated(), $request->ip()) === null) {
            // `error` (not `message`) so the site's forms can tell this apart from a plain throttle.
            return response()->json(['ok' => false, 'error' => CaptureLead::WAIT_FOR_REPLY], 429);
        }

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

    /** Delete one lead (from its pane) or several (ticked in the list). */
    public function destroy(DeleteInboxItemsRequest $request, DeleteFromInbox $action): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $user = $request->user();
        abort_if($user === null, 403);
        $count = $action->leads($request->ids(), $user);

        return back()->with('success', $count === 1 ? 'Lead deleted.' : "{$count} leads deleted.");
    }
}
