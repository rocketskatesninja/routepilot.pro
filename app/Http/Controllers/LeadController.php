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
use Inertia\Inertia;
use Inertia\Response;

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

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->role === 'tenant_admin', 403);

        $leads = Lead::query()->latest()->paginate(25)->withQueryString()
            ->through(fn (Lead $l): array => [
                'id' => $l->id,
                'name' => $l->name,
                'email' => $l->email,
                'phone' => $l->getAttribute('phone'),
                'message' => $l->getAttribute('message'),
                'source' => $l->source,
                'status' => $l->status,
                'on' => $l->created_at?->toDateString(),
            ]);

        return Inertia::render('leads/Index', ['leads' => $leads]);
    }

    public function updateStatus(Request $request, Lead $lead): RedirectResponse
    {
        abort_unless($request->user()?->role === 'tenant_admin', 403);

        $validated = $request->validate(['status' => ['required', 'in:new,contacted,converted,archived']]);
        $lead->forceFill(['status' => $validated['status']])->save();

        return back()->with('success', 'Lead updated.');
    }
}
