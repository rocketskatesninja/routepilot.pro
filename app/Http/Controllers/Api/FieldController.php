<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CompleteVisit;
use App\Actions\SendVisitFollowups;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteVisitRequest;
use App\Models\ChemicalInventory;
use App\Models\ChemicalReading;
use App\Models\FieldSyncKey;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Field PWA JSON API (session-authenticated, same-origin). One bundle endpoint
 * the offline app caches for the day, plus an idempotent visit-completion
 * endpoint its sync queue replays against. Worked by agents (and admins doing
 * routes); RouteStop isn't globally scoped, so ownership is verified via the
 * tenant-scoped Route.
 */
class FieldController extends Controller
{
    /** The agent's whole day in one payload — everything needed to work offline. */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $date = $request->date('date')?->toImmutable() ?? now()->toImmutable();

        $route = Route::query()
            ->where('agent_id', $user->id)
            ->whereDate('scheduled_date', $date)
            ->with([
                'stops' => fn ($q) => $q->orderBy('stop_order')->with([
                    'pool:id,name,type,sanitizer_type,volume_gallons,customer_id,custom_target_ranges',
                    'pool.customer:id,first_name,last_name,phone',
                    'pool.serviceLocation',
                    'pool.subscriptions' => fn ($s) => $s->where('status', 'active')->with('serviceType:id,name,tasks'),
                    'serviceVisit',
                ]),
            ])
            ->first();

        $stops = $route !== null ? $route->stops : collect();

        return response()->json([
            'date' => $date->toDateString(),
            'generated_at' => now()->toIso8601String(),
            'agent' => ['id' => $user->id, 'name' => $user->displayName()],
            'stops' => $stops->map(fn (RouteStop $stop): array => $this->stop($stop))->values()->all(),
            'inventory' => ChemicalInventory::query()
                ->orderBy('chemical_name')
                ->get(['id', 'chemical_name', 'unit', 'current_stock'])
                ->map(fn (ChemicalInventory $c): array => [
                    'id' => $c->id,
                    'name' => $c->chemical_name,
                    'unit' => $c->unit,
                    'stock' => (float) $c->current_stock,
                ])->all(),
        ]);
    }

    /** @return array<string, mixed> */
    private function stop(RouteStop $stop): array
    {
        $pool = $stop->pool;
        $serviceType = $pool?->subscriptions->isNotEmpty() === true ? $pool->subscriptions->first()->serviceType : null;
        $location = $pool?->serviceLocation;
        $coords = $pool?->coordinates();

        $last = $pool === null ? null : ChemicalReading::query()
            ->whereHas('serviceVisit', fn ($q) => $q->where('pool_id', $pool->id))
            ->latest()
            ->first();

        return [
            'id' => $stop->id,
            'order' => $stop->stop_order,
            'status' => $stop->status,
            'completed' => $stop->serviceVisit !== null,
            'eta' => $stop->estimated_arrival?->format('g:i A'),
            'pool' => $pool === null ? null : [
                'id' => $pool->id,
                'name' => $pool->name,
                'type' => $pool->type,
                'sanitizer' => $pool->sanitizer_type,
                'volume_gallons' => $pool->volume_gallons,
                'custom_target_ranges' => $pool->custom_target_ranges,
                'customer' => $pool->customer?->displayName() ?? '—',
                'phone' => $pool->customer?->phone,
                'gate_code' => $location?->getAttribute('gate_code'),
                'access_notes' => $location?->getAttribute('access_notes'),
                'lat' => $coords[0] ?? null,
                'lng' => $coords[1] ?? null,
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
        ];
    }

    /**
     * Complete a visit from the field app. Idempotent: replays carrying the same
     * `idempotency_key` return the original visit instead of completing twice.
     */
    public function complete(CompleteVisitRequest $request, RouteStop $stop, CompleteVisit $action, SendVisitFollowups $followups): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        $this->authorizeStop($request, $stop);

        $key = (string) $request->input('idempotency_key', '');
        if ($key !== '') {
            $existing = FieldSyncKey::query()->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                return response()->json(['ok' => true, 'idempotent' => true, 'visit_id' => $existing->service_visit_id]);
            }
        }

        $photos = $request->file('photos');
        $visit = $action->handle($stop, $request->validated(), $user, is_array($photos) ? $photos : []);
        $followups->handle($visit);

        if ($key !== '') {
            FieldSyncKey::query()->create([
                'idempotency_key' => $key,
                'user_id' => $user->id,
                'service_visit_id' => $visit->id,
            ]);
        }

        return response()->json(['ok' => true, 'idempotent' => false, 'visit_id' => $visit->id]);
    }

    /** The stop must belong to this tenant and be worked by this agent (or an admin). */
    private function authorizeStop(Request $request, RouteStop $stop): void
    {
        abort_if($stop->route === null, 404); // Route is tenant-scoped — a foreign stop resolves to null.
        $user = $request->user();
        $owns = $user instanceof User && ((int) $stop->route->getAttribute('agent_id') === $user->id || $user->role === 'tenant_admin');
        abort_unless($owns, 403);
    }
}
