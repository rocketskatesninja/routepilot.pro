<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\RouteStop;
use App\Services\RouteOptimizer;
use App\Services\SubscriptionMaterializer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Back-office Schedule screen — a day view of the materialized routes, one
 * card per agent with their ordered stops. The rich map + drag panel is a
 * later iteration; this is the read model behind it.
 */
class ScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeStaff($request);

        $date = $this->resolveDate((string) $request->string('date'));

        // Admins manage assignments by dragging stops on/off the per-day
        // "unassigned" route (agent_id null) — ensure it exists so it's always a
        // drop target. Agents only read, so no need to create it for them.
        $canManage = $request->user()?->role === 'tenant_admin';
        if ($canManage) {
            $this->unassignedRoute((int) $request->user()->tenant_id, $date);
        }

        $routes = Route::query()
            ->whereDate('scheduled_date', $date)
            ->whereNotNull('agent_id')
            ->with([
                'agent:id,first_name,last_name,avatar_path',
                'stops' => fn ($q) => $q->orderBy('stop_order')->with(['pool:id,name,customer_id,photo_path', 'pool.customer:id,first_name,last_name,photo_path']),
            ])
            ->get()
            ->map(fn (Route $route): array => $this->presentRoute($route))
            ->all();

        $unassignedRoute = Route::query()
            ->whereDate('scheduled_date', $date)
            ->whereNull('agent_id')
            ->with([
                'stops' => fn ($q) => $q->orderBy('stop_order')->with(['pool:id,name,customer_id,photo_path', 'pool.customer:id,first_name,last_name,photo_path']),
            ])
            ->first();

        return Inertia::render('schedule/Index', [
            'date' => $date,
            'today' => Carbon::today()->toDateString(),
            'routes' => $routes,
            'unassigned' => $unassignedRoute !== null ? $this->presentRoute($unassignedRoute) : null,
            'canManage' => $canManage,
        ]);
    }

    /** Materialize route stops from active subscriptions through 4 weeks out. */
    public function materialize(Request $request, SubscriptionMaterializer $materializer): RedirectResponse
    {
        abort_unless($request->user()?->role === 'tenant_admin', 403);

        $through = Carbon::today()->addWeeks(4)->toDateString();
        $created = $materializer->run((int) $request->user()->tenant_id, $through);

        return back()->with('success', $created > 0
            ? "Generated {$created} new stop".($created === 1 ? '' : 's')." through {$through}."
            : 'Schedule is already up to date — no new stops to add.');
    }

    /** Re-order a route's pending stops (nearest-neighbour + 2-opt). */
    public function optimize(Request $request, Route $route, RouteOptimizer $optimizer): RedirectResponse
    {
        abort_unless($request->user()?->role === 'tenant_admin', 403);

        $result = $optimizer->optimize($route);

        return back()->with('success', "Optimized {$result['optimized']} stops · {$result['total_distance_km']} km.");
    }

    /** Skip a single pending stop. */
    public function skipStop(Request $request, RouteStop $stop): RedirectResponse
    {
        abort_unless($request->user()?->role === 'tenant_admin', 403);
        // RouteStop isn't globally scoped; its Route is — a foreign stop's route resolves to null.
        abort_if($stop->route === null, 404);

        $stop->update(['status' => 'skipped', 'skip_reason' => 'Skipped by office']);

        return back()->with('success', 'Stop skipped.');
    }

    /**
     * Persist a drag-and-drop rearrangement of the day's stops: each route's
     * new ordered stop ids. A pending stop dragged onto another route is
     * reassigned to that route's agent; completed/skipped stops keep their
     * route and only have their order updated. Everything is tenant-scoped.
     */
    public function arrange(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->role === 'tenant_admin', 403);

        $validated = $request->validate([
            'routes' => ['array'],
            'routes.*.id' => ['required', 'integer'],
            'routes.*.stop_ids' => ['array'],
            'routes.*.stop_ids.*' => ['integer'],
        ]);

        $tenantId = (int) $request->user()->tenant_id;

        DB::transaction(function () use ($validated, $tenantId): void {
            foreach ((array) $validated['routes'] as $r) {
                if (! is_array($r)) {
                    continue;
                }
                $route = Route::query()->where('tenant_id', $tenantId)->find((int) ($r['id'] ?? 0));
                if ($route === null) {
                    continue;
                }
                foreach (array_values((array) ($r['stop_ids'] ?? [])) as $i => $stopId) {
                    $stop = RouteStop::query()
                        ->whereHas('route', fn ($q) => $q->where('tenant_id', $tenantId))
                        ->find((int) $stopId);
                    if ($stop === null) {
                        continue;
                    }
                    $stop->setAttribute('stop_order', $i + 1);
                    // Only a still-pending stop may change hands; done work stays put.
                    if ($stop->getAttribute('status') === 'pending') {
                        $stop->setAttribute('route_id', $route->id);
                    }
                    $stop->save();
                }
            }
        });

        return back();
    }

    /**
     * Shape one route (agent or the agent_id-null "unassigned" bucket) for the
     * day view. An unassigned route reports a null agent + label.
     *
     * @return array<string, mixed>
     */
    private function presentRoute(Route $route): array
    {
        $stops = $route->stops;

        return [
            'id' => $route->id,
            'agent' => $route->agent?->displayName(),
            'agent_photo' => $this->photoUrl($route->agent?->getAttribute('avatar_path')),
            'completed' => $stops->where('status', 'completed')->count(),
            'total' => $stops->count(),
            'stops' => $stops->map(fn (RouteStop $s): array => [
                'id' => $s->id,
                'order' => $s->stop_order,
                'pool' => $s->pool?->name,
                'pool_photo' => $this->photoUrl($s->pool?->getAttribute('photo_path')),
                'customer' => $s->pool?->customer?->displayName(),
                'status' => $s->status,
            ])->values()->all(),
        ];
    }

    /** The per-day unassigned route (agent_id null), created on first use. */
    private function unassignedRoute(int $tenantId, string $date): Route
    {
        $route = Route::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('agent_id')
            ->whereDate('scheduled_date', $date)
            ->first();

        if ($route === null) {
            // tenant_id is deliberately not fillable (charter: privilege fields
            // via forceFill at controlled sites).
            $route = new Route(['agent_id' => null, 'scheduled_date' => $date, 'status' => 'scheduled']);
            $route->forceFill(['tenant_id' => $tenantId])->save();
        }

        return $route;
    }

    private function resolveDate(string $input): string
    {
        try {
            return $input !== '' ? Carbon::parse($input)->toDateString() : Carbon::today()->toDateString();
        } catch (\Throwable) {
            return Carbon::today()->toDateString();
        }
    }
}
