<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
 * tenant's inbox. Lead status is set via controlled update (not mass-assigned).
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
}
