<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CompleteVisit;
use App\Http\Requests\AnalyzeReadingRequest;
use App\Http\Requests\CompleteVisitRequest;
use App\Models\ChemicalReading;
use App\Models\RouteStop;
use App\Services\ChemistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return Inertia::render('agent/Visit', [
            'stop' => ['id' => $stop->id, 'status' => $stop->status],
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

    public function complete(CompleteVisitRequest $request, RouteStop $stop, CompleteVisit $action): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        $this->authorizeStop($request, $stop);

        $photos = $request->file('photos');
        $action->handle($stop, $request->validated(), $user, is_array($photos) ? $photos : []);

        return redirect('/dashboard')->with('success', 'Visit completed.');
    }

    /** The stop must belong to this tenant and be worked by this agent (or an admin). */
    private function authorizeStop(Request $request, RouteStop $stop): void
    {
        abort_if($stop->route === null, 404); // Route is tenant-scoped — foreign stop resolves to null.
        $user = $request->user();
        $owns = $user !== null && ((int) $stop->route->getAttribute('agent_id') === $user->id || $user->role === 'tenant_admin');
        abort_unless($owns, 403);
    }
}
