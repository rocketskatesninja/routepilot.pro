<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceRequest;
use App\Models\ServiceVisit;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Assemble the data each ENABLED dashboard widget needs — only computes what's
 * shown (mirrors AssembleLandingData). Tenant-scoped via the bound tenant.
 */
class AssembleDashboardData
{
    /**
     * @param  list<string>  $enabled
     * @return array<string, mixed>
     */
    public function handle(User $user, array $enabled): array
    {
        $data = [];
        if (in_array('stats', $enabled, true)) {
            $data['stats'] = $this->stats($user);
        }
        if (in_array('route_map', $enabled, true)) {
            $data['route_map'] = $this->routeMap($user);
        }
        if (in_array('my_route', $enabled, true)) {
            $data['my_route'] = $this->myRoute($user);
        }
        if (in_array('requests', $enabled, true)) {
            $data['requests'] = $this->requests();
        }
        if (in_array('recent_visits', $enabled, true)) {
            $data['recent_visits'] = $this->recentVisits();
        }

        return $data;
    }

    /** @return array<string, int> */
    private function stats(User $user): array
    {
        $stops = Route::query()->whereDate('scheduled_date', today())->with('stops:id,route_id,status')->get()
            ->flatMap(fn (Route $r) => $r->stops);

        return [
            'today_stops' => $stops->count(),
            'completed_today' => $stops->where('status', 'completed')->count(),
            'remaining_today' => $stops->whereIn('status', ['pending', 'in_progress'])->count(),
            'agents' => User::query()->where('tenant_id', $user->getAttribute('tenant_id'))->where('role', 'agent')->where('is_active', true)->count(),
            'customers' => Customer::query()->count(),
            'pools' => Pool::query()->count(),
        ];
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
