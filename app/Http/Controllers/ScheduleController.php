<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\EstimateArrivals;
use App\Events\RouteUpdated;
use App\Models\AgentLocation;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceLocation;
use App\Models\User;
use App\Services\RouteOptimizer;
use App\Services\SubscriptionMaterializer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
        $canManage = $this->canManage($request->user());
        if ($canManage) {
            $this->unassignedRoute((int) $request->user()->tenant_id, $date);
        }

        $stopLoad = fn ($q) => $q->orderBy('stop_order')->with([
            'pool:id,name,customer_id,photo_path',
            'pool.customer:id,first_name,last_name,photo_path',
            'pool.serviceLocation:id,pool_id,lat,lng,address_line1,city,state,zip',
        ]);

        $routeModels = Route::query()
            ->whereDate('scheduled_date', $date)
            ->whereNotNull('agent_id')
            ->with(['agent:id,first_name,last_name,avatar_path,map_color', 'stops' => $stopLoad])
            ->get();

        $unassignedRoute = Route::query()
            ->whereDate('scheduled_date', $date)
            ->whereNull('agent_id')
            ->with(['stops' => $stopLoad])
            ->first();

        $browserKey = config('services.google.browser_maps_key');
        $tenant = $request->user()?->tenant;
        $hq = $tenant !== null && $tenant->lat !== null && $tenant->lng !== null
            ? ['lat' => (float) $tenant->lat, 'lng' => (float) $tenant->lng, 'label' => $tenant->formattedAddress()]
            : null;

        return Inertia::render('schedule/Index', [
            'date' => $date,
            'today' => Carbon::today()->toDateString(),
            'routes' => $routeModels->map(fn (Route $route): array => $this->presentRoute($route))->all(),
            'unassigned' => $unassignedRoute !== null ? $this->presentRoute($unassignedRoute) : null,
            'canManage' => $canManage,
            // An Agent+ may manage only their own route card; the UI matches this id.
            'manageAgentId' => $request->user()?->isAgentPlus() ? $request->user()->id : null,
            'coords' => $this->buildCoords($routeModels, $unassignedRoute),
            'hq' => $hq,
            'mapsKey' => is_string($browserKey) && $browserKey !== '' ? $browserKey : null,
            'agentLocations' => $this->liveAgents($date, $routeModels),
        ]);
    }

    /**
     * Live agent positions for the day's map — today only, recent pings, and
     * only for agents working today. Keyed by agent so the board can move each
     * marker in place from AgentLocationUpdated broadcasts.
     *
     * @param  Collection<int, Route>  $routeModels
     * @return list<array<string, mixed>>
     */
    private function liveAgents(string $date, Collection $routeModels): array
    {
        if ($date !== Carbon::today()->toDateString()) {
            return [];
        }

        $agents = $routeModels->mapWithKeys(fn (Route $r): array => [(int) $r->getAttribute('agent_id') => $r->agent]);

        return AgentLocation::query()
            ->where('recorded_at', '>=', now()->subMinutes(30))
            ->get()
            ->map(function (AgentLocation $loc) use ($agents): ?array {
                $agent = $agents->get((int) $loc->agent_id);

                return $agent === null ? null : [
                    'agent_id' => (int) $loc->agent_id,
                    'name' => $agent->displayName(),
                    'lat' => $loc->lat,
                    'lng' => $loc->lng,
                    'recorded_at' => $loc->recorded_at?->toIso8601String(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /** Materialize route stops from active subscriptions through 4 weeks out. */
    public function materialize(Request $request, SubscriptionMaterializer $materializer): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $through = Carbon::today()->addWeeks(4)->toDateString();
        $created = $materializer->run((int) $request->user()->tenant_id, $through);

        return back()->with('success', $created > 0
            ? "Generated {$created} new stop".($created === 1 ? '' : 's')." through {$through}."
            : 'Schedule is already up to date — no new stops to add.');
    }

    /**
     * Whether the user may manage a given route: admins manage every route, an
     * Agent+ manages only the route assigned to them.
     */
    private function canManageRoute(?User $user, ?Route $route): bool
    {
        if ($user === null || $route === null) {
            return false;
        }
        if ($this->canManage($user)) {
            return true;
        }

        return $user->isAgentPlus() && (int) $route->getAttribute('agent_id') === $user->id;
    }

    /** Re-order a route's pending stops (nearest-neighbour + 2-opt). */
    public function optimize(Request $request, Route $route, RouteOptimizer $optimizer, EstimateArrivals $arrivals): RedirectResponse
    {
        abort_unless($this->canManageRoute($request->user(), $route), 403);

        $result = $optimizer->optimize($route);
        $arrivals->handle($route);
        $this->broadcastRoute($route);

        return back()->with('success', "Optimized {$result['optimized']} stops · {$result['total_distance_km']} km.");
    }

    /** Skip a single pending stop (office, or the Agent+ assigned to its route). */
    public function skipStop(Request $request, RouteStop $stop): RedirectResponse
    {
        // RouteStop isn't globally scoped; its Route is — a foreign stop's route resolves to null.
        abort_if($stop->route === null, 404);
        $user = $request->user();
        abort_unless($this->canManageRoute($user, $stop->route), 403);

        $stop->update([
            'status' => 'skipped',
            'skip_reason' => $this->canManage($user) ? 'Skipped by office' : 'Skipped by tech',
        ]);
        $this->broadcastRoute($stop->route);

        return back()->with('success', 'Stop skipped.');
    }

    /** Push a live "this day's routes changed" event to the tenant's schedule board. */
    private function broadcastRoute(Route $route): void
    {
        $agentId = $route->getAttribute('agent_id');
        event(new RouteUpdated(
            (int) $route->getAttribute('tenant_id'),
            $route->scheduled_date->toDateString(),
            $agentId !== null ? (int) $agentId : null,
        ));
    }

    /**
     * Persist a drag-and-drop rearrangement of the day's stops: each route's
     * new ordered stop ids. A pending stop dragged onto another route is
     * reassigned to that route's agent; completed/skipped stops keep their
     * route and only have their order updated. Everything is tenant-scoped.
     */
    public function arrange(Request $request, EstimateArrivals $arrivals): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user) || $user?->isAgentPlus(), 403);

        $validated = $request->validate([
            'routes' => ['array'],
            'routes.*.id' => ['required', 'integer'],
            'routes.*.stop_ids' => ['array'],
            'routes.*.stop_ids.*' => ['integer'],
        ]);

        $tenantId = (int) $user->tenant_id;
        $isAdmin = $this->canManage($user);

        DB::transaction(function () use ($validated, $tenantId, $user, $isAdmin): void {
            foreach ((array) $validated['routes'] as $r) {
                if (! is_array($r)) {
                    continue;
                }
                $route = Route::query()->where('tenant_id', $tenantId)->find((int) ($r['id'] ?? 0));
                if ($route === null) {
                    continue;
                }
                // An Agent+ may only reorder their OWN route — never another's.
                if (! $isAdmin && (int) $route->getAttribute('agent_id') !== $user->id) {
                    continue;
                }
                foreach (array_values((array) ($r['stop_ids'] ?? [])) as $i => $stopId) {
                    $stop = RouteStop::query()
                        ->whereHas('route', fn ($q) => $q->where('tenant_id', $tenantId))
                        ->find((int) $stopId);
                    if ($stop === null) {
                        continue;
                    }
                    // Agents never reassign across routes — only reorder stops already on this one.
                    if (! $isAdmin && (int) $stop->getAttribute('route_id') !== $route->id) {
                        continue;
                    }
                    $stop->setAttribute('stop_order', $i + 1);
                    // Only an admin may move a still-pending stop between routes.
                    if ($isAdmin && $stop->getAttribute('status') === 'pending') {
                        $stop->setAttribute('route_id', $route->id);
                    }
                    $stop->save();
                }
            }
        });

        // Re-estimate arrivals for every route whose order may have changed.
        foreach ((array) $validated['routes'] as $r) {
            $route = is_array($r) ? Route::query()->where('tenant_id', $tenantId)->find((int) ($r['id'] ?? 0)) : null;
            if ($route !== null) {
                $arrivals->handle($route);
                $this->broadcastRoute($route);
            }
        }

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
            'agent_id' => $route->agent?->getKey(),
            'agent_photo' => $this->photoUrl($route->agent?->getAttribute('avatar_path')),
            'color' => $this->agentColor($route->agent),
            'completed' => $stops->where('status', 'completed')->count(),
            'total' => $stops->count(),
            'stops' => $stops->map(fn (RouteStop $s): array => [
                'id' => $s->id,
                'order' => $s->stop_order,
                'pool' => $s->pool?->name,
                'pool_photo' => $this->photoUrl($s->pool?->getAttribute('photo_path')),
                'customer' => $s->pool?->customer?->displayName(),
                'status' => $s->status,
                'eta' => $s->estimated_arrival?->format('g:i A'),
            ])->values()->all(),
        ];
    }

    /**
     * Geocoded coordinates for the day's stops, keyed by stop id. The map joins
     * these to the live (drag-reorderable) route lists client-side, so a stop
     * dragged between agents re-plots without a round-trip. Stops without
     * coordinates are omitted (the map simply can't place them). Tenant-scoped
     * via the Route global scope on the queries that built these collections.
     *
     * @param  Collection<int, Route>  $routes
     * @return array<int, array{lat: float, lng: float, address: string|null}>
     */
    private function buildCoords(Collection $routes, ?Route $unassigned): array
    {
        $all = $routes->all();
        if ($unassigned !== null) {
            $all[] = $unassigned;
        }

        $coords = [];
        foreach ($all as $route) {
            foreach ($route->stops as $stop) {
                $c = $stop->pool->coordinates();
                if ($c !== null) {
                    $coords[$stop->id] = ['lat' => $c[0], 'lng' => $c[1], 'address' => $this->formatAddress($stop->pool->serviceLocation)];
                }
            }
        }

        return $coords;
    }

    /** A one-line "street, city, ST zip" address for the map info window. */
    private function formatAddress(?ServiceLocation $loc): ?string
    {
        if ($loc === null) {
            return null;
        }
        $cityState = trim((string) $loc->getAttribute('state').' '.(string) $loc->getAttribute('zip'));
        $parts = array_filter([
            $loc->getAttribute('address_line1'),
            $loc->getAttribute('city'),
            $cityState !== '' ? $cityState : null,
        ]);

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    /** An agent's chosen route colour, falling back to the brand sky. */
    private function agentColor(?User $agent): string
    {
        $color = $agent?->getAttribute('map_color');

        return is_string($color) && $color !== '' ? $color : '#0ea5e9';
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
