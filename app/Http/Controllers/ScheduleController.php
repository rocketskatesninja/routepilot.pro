<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SkipStop;
use App\Actions\UnskipStop;
use App\Events\RouteUpdated;
use App\Models\AgentLocation;
use App\Models\ChemicalReading;
use App\Models\Route;
use App\Models\RouteStop;
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

        $user = $request->user();
        $date = $this->resolveDate((string) $request->string('date'));
        $canManage = $this->canManage($user);

        // Past days stay readable after a pool is archived or a tech removed.
        $stopLoad = fn ($q) => $q->orderBy('stop_order')->with([
            'pool' => fn ($p) => $p->withTrashed()->select('id', 'name', 'customer_id', 'photo_path'),
            'pool.customer:id,first_name,last_name,photo_path',
            'pool.serviceLocation:id,pool_id,lat,lng,address_line1,city,state,zip',
            // The panel under the map: the pool's last water test, with the day it was taken.
            'pool.latestReading' => fn ($q) => $q->addSelect('chemical_readings.*', 'service_visits.completed_at as tested_at'),
            'serviceSubscription.serviceType:id,name',
            'serviceVisit:id,route_stop_id',
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

        // An admin who runs stops too always gets their own card to drop visits into,
        // saved the first time one lands there (like the unassigned bucket). The
        // viewer's own route leads the list.
        $routes = $routeModels->map(fn (Route $route): array => $this->presentRoute($route));
        if ($canManage && ! $routes->contains('agent_id', $user->id)) {
            $routes->push($this->emptyRoute($user));
        }
        [$mine, $others] = $routes->partition(fn (array $r): bool => $r['agent_id'] === $user?->id);

        $browserKey = config('services.google.browser_maps_key');
        $tenant = $user?->tenant;
        $hq = $tenant !== null && $tenant->lat !== null && $tenant->lng !== null
            ? ['lat' => (float) $tenant->lat, 'lng' => (float) $tenant->lng, 'label' => $tenant->formattedAddress()]
            : null;

        return Inertia::render('schedule/Index', [
            'date' => $date,
            'today' => Tenant::localToday()->toDateString(),
            'routes' => $mine->concat($others)->values()->all(),
            // Admins always get the bucket as a drop target; it's saved the first time a stop lands in it.
            'unassigned' => $unassignedRoute !== null ? $this->presentRoute($unassignedRoute) : ($canManage ? $this->emptyRoute() : null),
            'canManage' => $canManage,
            // An Agent+ may manage only their own route card; the UI matches this id.
            'manageAgentId' => $user?->isAgentPlus() ? $user->id : null,
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
    public function optimize(Request $request, Route $route, RouteOptimizer $optimizer): RedirectResponse
    {
        abort_unless($this->canManageRoute($request->user(), $route), 403);

        $result = $optimizer->optimize($route);
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
     * tech), into the unassigned bucket, or onto their own route; those last two
     * are sent without an id until they first hold a stop. An Agent+ only
     * reorders their own route.
     */
    public function arrange(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canManage($user) || $user?->isAgentPlus(), 403);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'routes' => ['array'],
            'routes.*.id' => ['nullable', 'integer'],
            'routes.*.agent_id' => ['nullable', 'integer'],
            'routes.*.stop_ids' => ['array'],
            'routes.*.stop_ids.*' => ['integer'],
        ]);

        $isAdmin = $this->canManage($user);
        $date = Carbon::parse($validated['date'])->toDateString();

        $touched = DB::transaction(function () use ($validated, $user, $isAdmin, $date): array {
            $touched = [];
            foreach ($validated['routes'] ?? [] as $r) {
                $stopIds = array_map('intval', array_values($r['stop_ids'] ?? []));
                // An unsaved card is either the unassigned bucket or the admin's own route — never someone else's.
                $agentId = isset($r['agent_id']) ? (int) $r['agent_id'] : null;
                $route = isset($r['id'])
                    ? Route::query()->find((int) $r['id'])
                    : ($isAdmin && $stopIds !== [] && in_array($agentId, [null, $user->id], true) ? Route::forDay((int) $user->tenant_id, $agentId, $date) : null);
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

        // Refresh every route whose order may have changed.
        foreach ($touched as $route) {
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
        return [
            'id' => $route->id,
            'agent' => $route->agent?->displayName(),
            'agent_id' => $route->agent?->getKey(),
            'agent_photo' => $this->photoUrl($route->agent?->getAttribute('avatar_path')),
            'color' => $this->agentColor($route->agent),
            'stops' => $route->stops->map(fn (RouteStop $s): array => $this->presentStop($s))->values()->all(),
        ];
    }

    /**
     * One stop for its card, the map and the panel under the map. Its position
     * rides along, so a stop dragged to another route re-plots with no round-trip.
     *
     * @return array<string, mixed>
     */
    private function presentStop(RouteStop $s): array
    {
        $at = $s->pool?->coordinates();

        return [
            'id' => $s->id,
            'pool' => $s->pool?->name,
            'pool_photo' => $this->photoUrl($s->pool?->getAttribute('photo_path')),
            'customer' => $s->pool?->customer?->displayName(),
            'service' => $s->serviceSubscription?->serviceType?->name,
            'status' => $s->status,
            'pool_id' => $s->pool?->id,
            'customer_id' => $s->pool?->customer_id,
            'skip_reason' => $s->status === 'skipped' ? $s->skip_reason : null,
            // A finished stop's report is its completed visit.
            'report_id' => $s->status === 'completed' ? $s->serviceVisit?->id : null,
            'reading' => $this->presentReading($s->pool?->latestReading),
            'lat' => $at[0] ?? null,
            'lng' => $at[1] ?? null,
            'address' => $s->pool?->serviceLocation?->formattedAddress(),
        ];
    }

    /**
     * A pool's last water test for the detail panel: the day (company time) and
     * the main levels, or null when it has never been tested.
     *
     * @return array<string, float|string|null>|null
     */
    private function presentReading(?ChemicalReading $reading): ?array
    {
        if ($reading === null) {
            return null;
        }
        $testedAt = $reading->getAttribute('tested_at');

        return [
            'tested_on' => is_string($testedAt) ? Tenant::localDate(Carbon::parse($testedAt, 'UTC')) : null,
            'free_chlorine' => $reading->free_chlorine,
            'ph' => $reading->ph,
            'alkalinity' => $reading->alkalinity,
            'cyanuric_acid' => $reading->cyanuric_acid,
            'calcium_hardness' => $reading->calcium_hardness,
            'salt' => $reading->salt,
        ];
    }

    /** An agent's chosen route colour, falling back to the brand sky; the unassigned bucket is grey. */
    private function agentColor(?User $agent): string
    {
        if ($agent === null) {
            return '#9ca3af';
        }
        $color = $agent->getAttribute('map_color');

        return is_string($color) && $color !== '' ? $color : '#0ea5e9';
    }

    /**
     * A card for a route the day doesn't have yet: the unassigned bucket, or an
     * admin's own route.
     *
     * @return array<string, mixed>
     */
    private function emptyRoute(?User $agent = null): array
    {
        return [
            'id' => null,
            'agent' => $agent?->displayName(),
            'agent_id' => $agent?->id,
            'agent_photo' => $this->photoUrl($agent?->getAttribute('avatar_path')),
            'color' => $this->agentColor($agent),
            'stops' => [],
        ];
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
