<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CompleteVisit;
use App\Http\Requests\AnalyzeReadingRequest;
use App\Http\Requests\CompleteVisitRequest;
use App\Mail\VisitRecapMail;
use App\Models\ChemicalReading;
use App\Models\RouteStop;
use App\Models\ServiceVisit;
use App\Models\User;
use App\Models\VisitPhoto;
use App\Notifications\VisitCompleted;
use App\Services\BillingService;
use App\Services\ChemistryService;
use App\Support\LandingCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The at-pool visit flow — record a reading (with inline chemistry analysis),
 * treatments, tasks, and complete the stop. Worked by the stop's assigned
 * agent (or a tenant_admin). RouteStop isn't globally scoped, so ownership is
 * verified via its tenant-scoped Route.
 */
class VisitController extends Controller
{
    public function show(Request $request, RouteStop $stop): Response
    {
        $this->authorizeStop($request, $stop);

        $pool = $stop->pool;
        $pool->load([
            'customer:id,first_name,last_name',
            'serviceLocation',
            'subscriptions' => fn ($q) => $q->where('status', 'active')->with('serviceType:id,name,tasks'),
        ]);
        $serviceType = null;
        if ($pool->subscriptions->isNotEmpty()) {
            $serviceType = $pool->subscriptions->first()->serviceType;
        }

        $last = ChemicalReading::query()
            ->whereHas('serviceVisit', fn ($q) => $q->where('pool_id', $pool->id))
            ->latest()
            ->first();

        // If this stop already has a saved visit, load it so the form opens
        // pre-filled for editing instead of as a blank report.
        $existing = $stop->serviceVisit()
            ->with(['chemicalReading', 'treatments', 'tasks', 'photos'])
            ->first();

        return Inertia::render('agent/Visit', [
            'stop' => ['id' => $stop->id, 'status' => $stop->status],
            'visit' => $existing !== null ? $this->existingVisit($existing) : null,
            'pool' => [
                'name' => $pool->name,
                'customer' => $pool->customer?->displayName() ?? '—',
                'type' => $pool->type,
                'volume_gallons' => $pool->volume_gallons,
                'sanitizer' => $pool->sanitizer_type,
                'gate_code' => $pool->serviceLocation?->getAttribute('gate_code'),
                'access_notes' => $pool->serviceLocation?->getAttribute('access_notes'),
            ],
            'service' => [
                'name' => $serviceType?->name,
                'tasks' => $serviceType !== null ? array_values($serviceType->tasks ?? []) : [],
            ],
            'last_reading' => $last !== null ? [
                'on' => $last->created_at?->toDateString(),
                'free_chlorine' => $last->free_chlorine,
                'ph' => $last->ph,
                'alkalinity' => $last->alkalinity,
                'lsi_score' => $last->lsi_score,
            ] : null,
        ]);
    }

    public function analyze(AnalyzeReadingRequest $request, RouteStop $stop, ChemistryService $chem): JsonResponse
    {
        $this->authorizeStop($request, $stop);

        return response()->json($chem->fullAnalysis($request->validated(), $stop->pool));
    }

    public function complete(CompleteVisitRequest $request, RouteStop $stop, CompleteVisit $action, BillingService $billing): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        $this->authorizeStop($request, $stop);

        $photos = $request->file('photos');
        $visit = $action->handle($stop, $request->validated(), $user, is_array($photos) ? $photos : []);

        $customer = $visit->pool?->customer;

        // Notify the homeowner's portal user, if any (honors their preferences).
        $customerUserId = $customer?->getAttribute('user_id');
        if ($customerUserId !== null) {
            $customerUser = User::find($customerUserId);
            if ($customerUser !== null) {
                Notification::send($customerUser, new VisitCompleted($visit));
            }
        }

        // Per-visit recap email (skipped for opt-out customers).
        if ($customer !== null && is_string($customer->email) && $customer->email !== '' && ! $customer->email_opt_out) {
            $balance = $billing->outstandingForCustomer($customer);
            $payUrl = $balance > 0 ? URL::signedRoute('pay.link', ['customer' => $customer->id]) : null;
            Mail::to($customer->email)->queue(new VisitRecapMail($visit, $balance, $payUrl));
        }

        return redirect('/dashboard')->with('success', 'Visit completed.');
    }

    /** Feature / un-feature a visit photo in the public gallery (tenant_admin curation). */
    public function toggleShowcase(Request $request, VisitPhoto $photo): RedirectResponse
    {
        $this->authorizeAdmin($request);
        // VisitPhoto isn't tenant-scoped — assert ownership via its (scoped) visit.
        $visit = $photo->serviceVisit()->first();
        abort_if($visit === null, 404);

        $validated = $request->validate(['is_showcase' => ['required', 'boolean']]);
        $photo->update(['is_showcase' => (bool) $validated['is_showcase']]);
        LandingCache::forget((int) $visit->getAttribute('tenant_id'));

        return back();
    }

    /** The stop must belong to this tenant and be worked by this agent (or an admin). */
    private function authorizeStop(Request $request, RouteStop $stop): void
    {
        abort_if($stop->route === null, 404); // Route is tenant-scoped — foreign stop resolves to null.
        $user = $request->user();
        $owns = $user !== null && ((int) $stop->route->getAttribute('agent_id') === $user->id || $user->role === 'tenant_admin');
        abort_unless($owns, 403);
    }

    /**
     * Shape a saved visit for the form to pre-fill: reading values, treatments,
     * task done-states, notes, and read-only URLs for already-attached photos.
     *
     * @return array<string, mixed>
     */
    private function existingVisit(ServiceVisit $visit): array
    {
        $reading = $visit->chemicalReading;

        return [
            'id' => $visit->id,
            'notes' => $visit->notes,
            'reading' => $reading !== null ? [
                'free_chlorine' => $reading->free_chlorine,
                'total_chlorine' => $reading->total_chlorine,
                'ph' => $reading->ph,
                'alkalinity' => $reading->alkalinity,
                'calcium_hardness' => $reading->calcium_hardness,
                'cyanuric_acid' => $reading->cyanuric_acid,
                'salt' => $reading->salt,
                'water_temperature' => $reading->water_temperature,
            ] : null,
            'treatments' => $visit->treatments->map(fn ($t) => [
                'name' => $t->chemical_name,
                'amount' => $t->amount,
                'unit' => $t->unit,
            ])->values()->all(),
            'tasks' => $visit->tasks->map(fn ($t) => [
                'name' => $t->task_name,
                'done' => (bool) $t->is_completed,
            ])->values()->all(),
            'photos' => $visit->photos
                ->map(fn ($p) => $this->photoUrl($p->getAttribute('photo_path')))
                ->filter()
                ->values()
                ->all(),
        ];
    }
}
