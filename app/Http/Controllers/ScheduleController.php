<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\EstimateArrivals;
use App\Actions\SkipStop;
use App\Actions\UnskipStop;
use App\Events\RouteUpdated;
use App\Models\AgentLocation;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceLocation;
use App\Models\Tenant;
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
 * Back-office Schedule screen: a day view of the materialized routes, one card
 * per agent with their ordered stops, plus the day's "unassigned" bucket, a
 * map, and the actions that change a day (rearrange, optimize, skip).
 */
class ScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeStaff($request);

        $date = $this->resolveDate((string) $request->string('date'));
        $canManage = $this->canManage($request->user());

        // Past days stay readable after a pool is archived or a tech removed.
        $stopLoad = fn ($q) => $q->orderBy('stop_order')->with([
            'pool' => fn ($p) => $p->withTrashed()->select('id', 'name', 'customer_id', 'photo_path'),
            'pool.customer:id,first_name,last_name,photo_path',
            'pool.serviceLocation:id,pool_id,lat,lng,address_line1,city,state,zip',
            'serviceSubscription.serviceType:id,name',
        ]);

        $routeModels = Route::query()
            ->whereDate('scheduled_date', $date)
            ->whereNotNull('agent_id')
            ->with([
                'agent' => fn ($q) => $q->withTrashed()->select('id', 'first_name', 'last_name', 'avatar_path', 'map_color'),
                'stops' => $stopLoad,
            ])
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
            'today' => Tenant::localToday()->toDateString(),
            'routes' => $routeModels->map(fn (Route $route): array => $this->presentRoute($route))->all(),
            // Admins always get the bucket as a drop target; it's saved the first time a stop lands in it.
            'unassigned' => $unassignedRoute !== null ? $this->presentRoute($unassignedRoute) : ($canManage ? $this->emptyBucket() : null),
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
        if ($date !== Tenant::localToday()->toDateString()) {
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

    /** Put any plan visits that are missing onto the routes, over the same window the nightly job uses. */
    public function materialize(Request $request, SubscriptionMaterializer $materializer): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $tenantId = (int) $request->user()->tenant_id;
        $created = $materializer->run($tenantId);
        $through = Carbon::parse($materializer->windowEnd($tenantId))->format('M j');

        return back()->with('success', $created > 0
            ? "Added {$created} stop".($created === 1 ? '' : 's')." through {$through}."
            : "The schedule is already up to date through {$through}.");
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
        event(RouteUpdated::for($route));

        return back()->with('success', "Optimized {$result['optimized']} stops · {$result['total_distance_km']} km.");
    }

    /** Skip a stop that's still due (office, or the Agent+ assigned to its route). */
    public function skipStop(Request $request, RouteStop $stop, SkipStop $skip): RedirectResponse
    {
        // RouteStop isn't globally scoped; its Route is, so a foreign stop's route resolves to null.
        abort_if($stop->route === null, 404);
        $user = $request->user();
        abort_unless($this->canManageRoute($user, $stop->route), 403);

        return $skip->handle($stop, $this->canManage($user) ? 'Skipped by office' : 'Skipped by tech')
            ? back()->with('success', 'Stop skipped.')
            : back()->with('error', 'Only a stop that is still due can be skipped.');
    }

    /** Put a skipped stop back (office, or the Agent+ assigned to its route). */
    public function unskipStop(Request $request, RouteStop $stop, UnskipStop $unskip): RedirectResponse
    {
        abort_if($stop->route === null, 404);
        abort_unless($this->canManageRoute($request->user(), $stop->route), 403);

        $unskip->handle($stop);

        return back()->with('success', 'Stop restored.');
    }

    /**
     * Save a drag-and-drop rearrangement of one day: each route's ordered stop
     * ids. Only that day's routes and stops are touched. An admin may move a
     * still-due stop onto another tech's route (that day only; the plan keeps its
     * tech) or into the unassigned bucket, which is sent without an id until it
     * first holds a stop. An Agent+ only reorders their own route.
     */
    public function arrange(Request $request, EstimateArrivals $arrivals): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user) || $user?->isAgentPlus(), 403);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'routes' => ['array'],
            'routes.*.id' => ['nullable', 'integer'],
            'routes.*.stop_ids' => ['array'],
            'routes.*.stop_ids.*' => ['integer'],
        ]);

        $isAdmin = $this->canManage($user);
        $date = Carbon::parse($validated['date'])->toDateString();

        $touched = DB::transaction(function () use ($validated, $user, $isAdmin, $date): array {
            $touched = [];
            foreach ($validated['routes'] ?? [] as $r) {
                $stopIds = array_map('intval', array_values($r['stop_ids'] ?? []));
                $route = isset($r['id'])
                    ? Route::query()->find((int) $r['id'])
                    : ($isAdmin && $stopIds !== [] ? Route::forDay((int) $user->tenant_id, null, $date) : null);
                if ($route === null || $route->scheduled_date->toDateString() !== $date) {
                    continue;
                }
                if (! $isAdmin && (int) $route->getAttribute('agent_id') !== $user->id) {
                    continue; // an Agent+ may only reorder their own route
                }

                $stops = RouteStop::query()
                    ->whereKey($stopIds)
                    ->whereHas('route', fn ($q) => $q->whereDate('scheduled_date', $date))
                    ->get()
                    ->keyBy('id');
                foreach ($stopIds as $i => $id) {
                    $stop = $stops->get($id);
                    if ($stop === null) {
                        continue;
                    }
                    $moving = (int) $stop->getAttribute('route_id') !== $route->id;
                    if ($moving && ! ($isAdmin && $stop->status === 'pending')) {
                        continue; // only an admin moves a stop, and only one still due
                    }
                    $stop->update(['route_id' => $route->id, 'stop_order' => $i + 1]);
                }
                $touched[$route->id] = $route;
            }

            return $touched;
        });

        // Re-time and refresh every route whose order may have changed.
        foreach ($touched as $route) {
            $arrivals->handle($route);
            event(RouteUpdated::for($route));
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
                'service' => $s->serviceSubscription?->serviceType?->name,
                'status' => $s->status,
                'eta' => Tenant::localTime($s->estimated_arrival),
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

    /**
     * The unassigned bucket for a day that has no unassigned route yet.
     *
     * @return array<string, mixed>
     */
    private function emptyBucket(): array
    {
        return ['id' => null, 'agent' => null, 'agent_id' => null, 'agent_photo' => null, 'color' => $this->agentColor(null), 'completed' => 0, 'total' => 0, 'stops' => []];
    }

    private function resolveDate(string $input): string
    {
        try {
            return $input !== '' ? Carbon::parse($input)->toDateString() : Tenant::localToday()->toDateString();
        } catch (\Throwable) {
            return Tenant::localToday()->toDateString();
        }
    }
}
