<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceRequest;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use App\Services\ChemistryService;
use App\Services\WeatherService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;

/**
 * Assemble the data each ENABLED dashboard widget needs — only computes what's
 * shown (mirrors AssembleLandingData). Tenant-scoped via the bound tenant.
 */
class AssembleDashboardData
{
    private ?Customer $customerMemo = null;

    private bool $customerLoaded = false;

    /** @var array<string, mixed>|null */
    private ?array $weatherMemo = null;

    private bool $weatherLoaded = false;

    /**
     * @param  list<string>  $enabled
     * @return array<string, mixed>
     */
    public function handle(User $user, array $enabled): array
    {
        $builders = [
            // tenant_admin
            'stats' => fn () => $this->stats($user),
            'route_map' => fn () => $this->routeMap($user),
            'my_route' => fn () => $this->myRoute($user),
            'requests' => fn () => $this->requests(),
            'recent_visits' => fn () => $this->recentVisits(),
            'week_strip' => fn () => $this->weekStrip($user),
            'today_stops' => fn () => $this->todayStops(),
            'weather' => fn () => $this->weather($user),
            'billing_summary' => fn () => $this->billing(),
            'notifications' => fn () => $this->notifications($user),
            // agent
            'agent_stats' => fn () => $this->agentStats($user),
            'agent_route' => fn () => $this->agentRoute($user),
            'agent_visits' => fn () => $this->agentVisits($user),
            // customer
            'my_pools' => fn () => $this->myPools($user),
            'next_visit' => fn () => $this->nextVisit($user),
            'account_balance' => fn () => $this->accountBalance($user),
            'customer_visits' => fn () => $this->customerVisits($user),
            // super_admin
            'platform_stats' => fn () => $this->platformStats(),
            'recent_tenants' => fn () => $this->recentTenants(),
        ];

        $data = [];
        foreach ($builders as $key => $build) {
            if (in_array($key, $enabled, true)) {
                $data[$key] = $build();
            }
        }

        return $data;
    }

    /**
     * The next seven days as a strip of stop counts (total + completed), with a
     * daily weather code per day when the tenant has a geocoded address.
     *
     * @return array<string, mixed>
     */
    private function weekStrip(User $user): array
    {
        $routes = Route::query()
            ->whereBetween('scheduled_date', [today(), today()->addDays(6)])
            ->with('stops:id,route_id,status')->get();

        $weatherByDate = $this->weatherByDate($user);

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = today()->addDays($i);
            $stops = $routes->filter(fn (Route $r) => $r->scheduled_date->isSameDay($date))->flatMap(fn (Route $r) => $r->stops);
            $weather = $weatherByDate[$date->toDateString()] ?? null;
            $days[] = [
                'date' => $date->toDateString(),
                'dow' => $date->isoFormat('dd'),
                'day' => (int) $date->format('j'),
                'total' => $stops->count(),
                'completed' => $stops->where('status', 'completed')->count(),
                'is_today' => $date->isToday(),
                'code' => $weather['code'] ?? null,
                'high' => $weather['high'] ?? null,
                'low' => $weather['low'] ?? null,
            ];
        }

        return ['days' => $days];
    }

    /**
     * Map of date => {code, high, low} from the tenant's forecast (empty when no
     * address/forecast).
     *
     * @return array<string, array{code: int, high: int, low: int}>
     */
    private function weatherByDate(User $user): array
    {
        $weather = $this->weather($user);
        $forecastDays = is_array($weather['days'] ?? null) ? $weather['days'] : [];

        $map = [];
        foreach ($forecastDays as $day) {
            if (is_array($day) && isset($day['date'], $day['code'], $day['high'], $day['low'])) {
                $map[(string) $day['date']] = [
                    'code' => (int) $day['code'],
                    'high' => (int) $day['high'],
                    'low' => (int) $day['low'],
                ];
            }
        }

        return $map;
    }

    /** @return list<array<string, mixed>> */
    private function todayStops(): array
    {
        return RouteStop::query()
            ->whereHas('route', fn ($q) => $q->whereDate('scheduled_date', today()))
            ->with(['pool:id,name,photo_path', 'route:id,agent_id', 'route.agent:id,first_name,last_name'])
            ->orderBy('route_id')->orderBy('stop_order')->limit(50)->get()
            ->map(fn (RouteStop $s) => [
                'id' => $s->id,
                'pool' => $s->pool?->getAttribute('name'),
                'pool_photo' => $this->photoUrl($s->pool?->getAttribute('photo_path')),
                'agent' => $s->route?->agent !== null ? $this->name($s->route->agent) : 'Unassigned',
                'status' => $s->status,
            ])->all();
    }

    /**
     * The tenant's forecast, memoized — both the weather and week-strip widgets
     * use it, so it's computed at most once per request.
     *
     * @return array<string, mixed>|null
     */
    private function weather(User $user): ?array
    {
        if ($this->weatherLoaded) {
            return $this->weatherMemo;
        }
        $this->weatherLoaded = true;

        $tenant = $user->tenant;
        if ($tenant === null || $tenant->lat === null || $tenant->lng === null) {
            return null;
        }

        return $this->weatherMemo = app(WeatherService::class)->forecast((float) $tenant->lat, (float) $tenant->lng);
    }

    /** @return array<string, mixed> */
    private function billing(): array
    {
        $balances = app(BillingService::class)->outstandingBalances();

        return [
            'outstanding_total' => round((float) $balances->sum('balance'), 2),
            'customer_count' => $balances->count(),
            'top' => $balances->take(6)->map(fn (array $row) => [
                'customer' => $row['customer']->displayName(),
                'balance' => round((float) $row['balance'], 2),
            ])->values()->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function notifications(User $user): array
    {
        return $user->notifications()->latest()->limit(8)->get()
            ->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Notification',
                'body' => $n->data['body'] ?? '',
                'url' => $n->data['url'] ?? null,
                'read' => $n->read_at !== null,
                'on' => $n->created_at?->diffForHumans(),
            ])->all();
    }

    /** @return array{tiles: list<array<string, mixed>>} */
    private function stats(User $user): array
    {
        $stops = Route::query()->whereDate('scheduled_date', today())->with('stops:id,route_id,status')->get()
            ->flatMap(fn (Route $r) => $r->stops);

        return ['tiles' => [
            ['label' => 'Stops today', 'value' => $stops->count()],
            ['label' => 'Completed', 'value' => $stops->where('status', 'completed')->count(), 'accent' => 'text-emerald-600 dark:text-emerald-400'],
            ['label' => 'Remaining', 'value' => $stops->whereIn('status', ['pending', 'in_progress'])->count()],
            ['label' => 'Agents', 'value' => User::query()->where('tenant_id', $user->getAttribute('tenant_id'))->where('role', 'agent')->where('is_active', true)->count()],
            ['label' => 'Customers', 'value' => Customer::query()->count()],
            ['label' => 'Pools', 'value' => Pool::query()->count()],
        ]];
    }

    /**
     * Agent: today's progress (stops + this week).
     *
     * @return array{tiles: list<array<string, mixed>>}
     */
    private function agentStats(User $user): array
    {
        $route = Route::query()->where('agent_id', $user->id)->whereDate('scheduled_date', today())
            ->with('stops:id,route_id,status')->first();
        $stops = $route !== null ? $route->stops : collect();
        $week = ServiceVisit::query()->where('agent_id', $user->id)->where('status', 'completed')
            ->where('completed_at', '>=', now()->startOfWeek())->count();

        return ['tiles' => [
            ['label' => 'Stops today', 'value' => $stops->count()],
            ['label' => 'Completed', 'value' => $stops->where('status', 'completed')->count(), 'accent' => 'text-emerald-600 dark:text-emerald-400'],
            ['label' => 'Remaining', 'value' => $stops->whereIn('status', ['pending', 'in_progress'])->count()],
            ['label' => 'Done this week', 'value' => $week],
        ]];
    }

    /**
     * Agent: today's route stops (rendered by the My-route widget).
     *
     * @return array<string, mixed>
     */
    private function agentRoute(User $user): array
    {
        $route = Route::query()->where('agent_id', $user->id)->whereDate('scheduled_date', today())
            ->with(['stops' => fn ($q) => $q->with('pool:id,name,photo_path')->orderBy('stop_order')])->first();
        $stops = $route !== null ? $route->stops : collect();

        return [
            'label' => 'today',
            'stops' => $stops->map(fn (RouteStop $s) => [
                'id' => $s->id,
                'pool' => $s->pool?->getAttribute('name'),
                'pool_photo' => $this->photoUrl($s->pool?->getAttribute('photo_path')),
                'status' => $s->status,
            ])->values()->all(),
        ];
    }

    /**
     * Customer: their pools with a chemistry health status.
     *
     * @return array{pools: list<array<string, mixed>>}
     */
    private function myPools(User $user): array
    {
        $customer = $this->customerFor($user);
        if ($customer === null) {
            return ['pools' => []];
        }
        $chem = app(ChemistryService::class);

        return ['pools' => $customer->pools->map(function (Pool $pool) use ($chem): array {
            $reading = $pool->visits()->where('status', 'completed')->latest('completed_at')->with('chemicalReading')->first()?->chemicalReading;
            $health = $reading === null ? null : $chem->getLSIStatus((float) ($reading->lsi_score ?? $chem->calculateLSI([
                'temperature' => $reading->water_temperature, 'ph' => $reading->ph,
                'alkalinity' => $reading->alkalinity, 'calcium_hardness' => $reading->calcium_hardness, 'salt' => $reading->salt,
            ])));

            return ['id' => $pool->id, 'name' => $pool->name, 'photo' => $this->photoUrl($pool->getAttribute('photo_path')), 'health' => $health];
        })->all()];
    }

    /**
     * Customer: their next scheduled visit.
     *
     * @return array<string, mixed>|null
     */
    private function nextVisit(User $user): ?array
    {
        $customer = $this->customerFor($user);
        if ($customer === null) {
            return null;
        }

        $next = RouteStop::query()
            ->whereIn('pool_id', $customer->pools->pluck('id'))->where('status', 'pending')
            ->whereHas('route', fn ($q) => $q->whereDate('scheduled_date', '>=', today()))
            ->with(['route:id,scheduled_date', 'pool:id,name'])->get()
            ->sortBy(fn (RouteStop $s) => $s->route?->scheduled_date)->first();

        return $next !== null ? [
            'pool' => $next->pool?->getAttribute('name'),
            'date' => $next->route?->scheduled_date?->toDateString(),
        ] : null;
    }

    /**
     * Customer: outstanding balance + autopay state.
     *
     * @return array<string, mixed>
     */
    private function accountBalance(User $user): array
    {
        $customer = $this->customerFor($user);

        return [
            'balance' => $customer !== null ? app(BillingService::class)->outstandingForCustomer($customer) : 0.0,
            'autopay' => $customer !== null && (bool) $customer->getAttribute('autopay_enabled'),
        ];
    }

    /**
     * Customer: recent visits to their pools (rendered by the Recent-visits widget).
     *
     * @return list<array<string, mixed>>
     */
    private function customerVisits(User $user): array
    {
        $customer = $this->customerFor($user);
        if ($customer === null) {
            return [];
        }

        return ServiceVisit::query()
            ->whereIn('pool_id', $customer->pools->pluck('id'))->where('status', 'completed')
            ->with(['pool:id,name,photo_path', 'agent:id,first_name,last_name,avatar_path'])
            ->latest('completed_at')->limit(6)->get()
            ->map(fn (ServiceVisit $v) => [
                'id' => $v->id,
                'pool' => $v->pool?->getAttribute('name'),
                'pool_photo' => $this->photoUrl($v->pool?->getAttribute('photo_path')),
                'agent' => $this->name($v->agent),
                'agent_photo' => $this->photoUrl($v->agent?->getAttribute('avatar_path')),
                'completed_on' => $v->completed_at?->toDateString(),
            ])->all();
    }

    /** The acting customer record (memoized per request). */
    private function customerFor(User $user): ?Customer
    {
        if (! $this->customerLoaded) {
            $this->customerMemo = Customer::query()->where('user_id', $user->id)->with('pools')->first();
            $this->customerLoaded = true;
        }

        return $this->customerMemo;
    }

    /**
     * Super-admin: platform-wide totals (cross-tenant).
     *
     * @return array{tiles: list<array<string, mixed>>}
     */
    private function platformStats(): array
    {
        return ['tiles' => [
            ['label' => 'Tenants', 'value' => Tenant::query()->count()],
            ['label' => 'Active', 'value' => Tenant::query()->where('status', 'active')->count(), 'accent' => 'text-emerald-600 dark:text-emerald-400'],
            ['label' => 'Users', 'value' => User::query()->where('role', '!=', 'super_admin')->count()],
            ['label' => 'Pools', 'value' => Pool::query()->count()],
            ['label' => 'Visits/wk', 'value' => ServiceVisit::query()->where('status', 'completed')->where('completed_at', '>=', now()->startOfWeek())->count()],
        ]];
    }

    /**
     * Super-admin: the newest tenants.
     *
     * @return list<array<string, mixed>>
     */
    private function recentTenants(): array
    {
        return Tenant::query()->latest()->limit(6)->get()->map(fn (Tenant $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'status' => $t->getAttribute('status'),
            'logo' => $this->photoUrl($t->getAttribute('logo_path')),
        ])->all();
    }

    /**
     * Today's stops across all agents, as numbered map markers colored by agent,
     * plus the tenant HQ. Stops without geocoded coordinates are dropped. The
     * browser maps key rides along so the widget is self-contained; the widget
     * degrades gracefully when it's absent.
     *
     * @return array<string, mixed>
     */
    private function routeMap(User $user): array
    {
        $browserKey = config('services.google.browser_maps_key');

        $routes = Route::query()
            ->whereDate('scheduled_date', today())
            ->with([
                'agent:id,first_name,last_name,map_color',
                'stops' => fn ($q) => $q->orderBy('stop_order')->with(['pool:id,name', 'pool.serviceLocation:id,pool_id,lat,lng']),
            ])
            ->get();

        $markers = [];
        foreach ($routes as $route) {
            $color = $this->agentColor($route->agent);
            $agent = $route->agent !== null ? $this->name($route->agent) : null;
            foreach ($route->stops as $stop) {
                $coords = $stop->pool?->coordinates();
                if ($coords === null) {
                    continue;
                }
                $markers[] = [
                    'lat' => $coords[0],
                    'lng' => $coords[1],
                    'order' => $stop->stop_order,
                    'pool' => $stop->pool->getAttribute('name'),
                    'status' => $stop->status,
                    'agent' => $agent,
                    'color' => $color,
                ];
            }
        }

        $tenant = $user->tenant;
        $hq = $tenant !== null && $tenant->lat !== null && $tenant->lng !== null
            ? ['lat' => $tenant->lat, 'lng' => $tenant->lng, 'label' => $tenant->formattedAddress()]
            : null;

        return [
            'maps_key' => is_string($browserKey) && $browserKey !== '' ? $browserKey : null,
            'hq' => $hq,
            'markers' => $markers,
        ];
    }

    private function agentColor(?User $agent): string
    {
        $color = $agent?->getAttribute('map_color');

        return is_string($color) && $color !== '' ? $color : '#0ea5e9';
    }

    /** @return array<string, mixed> */
    private function myRoute(User $user): array
    {
        $route = Route::query()
            ->where('agent_id', $user->id)
            ->whereDate('scheduled_date', '>=', today())
            ->whereHas('stops', fn ($q) => $q->where('status', 'pending'))
            ->with(['stops' => fn ($q) => $q->with('pool:id,name,photo_path')->orderBy('stop_order')])
            ->orderBy('scheduled_date')
            ->first();

        $date = $route?->scheduled_date;
        $stops = $route !== null ? $route->stops : collect();

        return [
            'label' => $date === null ? null : ($date->isToday() ? 'today' : $date->isoFormat('ddd, MMM D')),
            'stops' => $stops->map(fn (RouteStop $s) => [
                'id' => $s->id,
                'pool' => $s->pool?->getAttribute('name'),
                'pool_photo' => $this->photoUrl($s->pool?->getAttribute('photo_path')),
                'status' => $s->status,
            ])->values()->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function requests(): array
    {
        return ServiceRequest::query()
            ->where('status', 'pending')->with(['customer:id,first_name,last_name,photo_path', 'pool:id,name,photo_path'])
            ->latest()->limit(8)->get()
            ->map(fn (ServiceRequest $r) => [
                'id' => $r->id,
                'type' => $r->type,
                'message' => $r->message,
                'customer' => $r->customer?->displayName(),
                'customer_photo' => $this->photoUrl($r->customer?->getAttribute('photo_path')),
                'pool' => $r->pool?->getAttribute('name'),
                'preferred_date' => $r->preferred_date?->toDateString(),
                'on' => $r->created_at?->toDateString(),
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function recentVisits(): array
    {
        return ServiceVisit::query()
            ->where('status', 'completed')->with(['pool:id,name,photo_path', 'agent:id,first_name,last_name,avatar_path'])
            ->latest('completed_at')->limit(6)->get()
            ->map(fn (ServiceVisit $v) => [
                'id' => $v->id,
                'pool' => $v->pool?->getAttribute('name'),
                'pool_photo' => $this->photoUrl($v->pool?->getAttribute('photo_path')),
                'agent' => $this->name($v->agent),
                'agent_photo' => $this->photoUrl($v->agent?->getAttribute('avatar_path')),
                'completed_on' => $v->completed_at?->toDateString(),
            ])->all();
    }

    /**
     * The agent's own recently completed visits (reuses the Recent Visits card).
     *
     * @return list<array<string, mixed>>
     */
    private function agentVisits(User $user): array
    {
        return ServiceVisit::query()
            ->where('agent_id', $user->id)
            ->where('status', 'completed')->with(['pool:id,name,photo_path', 'agent:id,first_name,last_name,avatar_path'])
            ->latest('completed_at')->limit(6)->get()
            ->map(fn (ServiceVisit $v) => [
                'id' => $v->id,
                'pool' => $v->pool?->getAttribute('name'),
                'pool_photo' => $this->photoUrl($v->pool?->getAttribute('photo_path')),
                'agent' => $this->name($v->agent),
                'agent_photo' => $this->photoUrl($v->agent?->getAttribute('avatar_path')),
                'completed_on' => $v->completed_at?->toDateString(),
            ])->all();
    }

    private function photoUrl(mixed $path): ?string
    {
        return is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
    }

    private function name(?User $user): string
    {
        if ($user === null) {
            return '—';
        }
        $name = trim((string) $user->getAttribute('first_name').' '.(string) $user->getAttribute('last_name'));

        return $name !== '' ? $name : '—';
    }
}
