<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceRequest;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ChemistryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The dashboard is role-adaptive: each account type lands on a different
 * surface with its own data. Tenant scoping is automatic for staff/customer
 * roles (ResolveTenant binds the session user's tenant); the super-admin has
 * no tenant bound and so sees cross-tenant platform totals.
 */
class DashboardController extends Controller
{
    public function index(Request $request, ChemistryService $chem): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return match ($user->role) {
            'super_admin' => Inertia::render('dashboards/Platform', $this->platform()),
            'agent' => Inertia::render('dashboards/Agent', $this->agent($user)),
            'customer' => Inertia::render('dashboards/Customer', $this->customer($user, $chem)),
            default => Inertia::render('dashboards/Admin', $this->admin()),
        };
    }

    /** @return array<string, mixed> */
    private function platform(): array
    {
        return [
            'stats' => [
                'tenants' => Tenant::query()->count(),
                'active_tenants' => Tenant::query()->where('status', 'active')->count(),
                'users' => User::query()->where('role', '!=', 'super_admin')->count(),
                'pools' => Pool::query()->count(),
                'visits_this_week' => ServiceVisit::query()->where('status', 'completed')->where('completed_at', '>=', now()->startOfWeek())->count(),
            ],
            'recent_tenants' => Tenant::query()->latest()->limit(5)->get()->map(fn (Tenant $t) => [
                'id' => $t->id, 'name' => $t->name, 'status' => $t->getAttribute('status'),
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function admin(): array
    {
        $todayRoutes = Route::query()->whereDate('scheduled_date', today())->with('stops:id,route_id,status')->get();
        $stops = $todayRoutes->flatMap(fn (Route $r) => $r->stops);

        return [
            'stats' => [
                'today_stops' => $stops->count(),
                'completed_today' => $stops->where('status', 'completed')->count(),
                'remaining_today' => $stops->whereIn('status', ['pending', 'in_progress'])->count(),
                'agents' => User::query()->where('tenant_id', app('tenant_id'))->where('role', 'agent')->where('is_active', true)->count(),
                'customers' => Customer::query()->count(),
                'pools' => Pool::query()->count(),
            ],
            'recent_visits' => ServiceVisit::query()
                ->where('status', 'completed')->with(['pool:id,name,photo_path', 'agent:id,first_name,last_name,avatar_path'])
                ->latest('completed_at')->limit(6)->get()
                ->map(fn (ServiceVisit $v) => [
                    'id' => $v->id,
                    'pool' => $v->pool?->getAttribute('name'),
                    'pool_photo' => $this->photoUrl($v->pool?->getAttribute('photo_path')),
                    'agent' => $this->name($v->agent),
                    'agent_photo' => $this->photoUrl($v->agent?->getAttribute('avatar_path')),
                    'completed_on' => $v->completed_at?->toDateString(),
                ])->all(),
            'pending_requests' => ServiceRequest::query()
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
                ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function agent(User $user): array
    {
        $route = Route::query()
            ->where('agent_id', $user->id)
            ->whereDate('scheduled_date', today())
            ->with(['stops' => fn ($q) => $q->with('pool:id,name,customer_id,photo_path')->orderBy('stop_order')])
            ->first();

        $weekCompleted = ServiceVisit::query()
            ->where('agent_id', $user->id)->where('status', 'completed')
            ->where('completed_at', '>=', now()->startOfWeek())->count();

        $stops = $route !== null ? $route->stops : collect();

        return [
            'agent_name' => $this->name($user),
            'stats' => [
                'today_total' => $stops->count(),
                'completed_today' => $stops->where('status', 'completed')->count(),
                'remaining_today' => $stops->whereIn('status', ['pending', 'in_progress'])->count(),
                'week_completed' => $weekCompleted,
            ],
            'today_stops' => $stops->map(fn (RouteStop $s) => [
                'id' => $s->id,
                'pool' => $s->pool?->getAttribute('name'),
                'pool_photo' => $this->photoUrl($s->pool?->getAttribute('photo_path')),
                'status' => $s->status,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function customer(User $user, ChemistryService $chem): array
    {
        $customer = Customer::query()->where('user_id', $user->id)->with('pools')->first();
        if ($customer === null) {
            return ['customer_name' => $this->name($user), 'pools' => [], 'next_visit' => null, 'recent_visits' => []];
        }

        $poolIds = $customer->pools->pluck('id');

        $pools = $customer->pools->map(function (Pool $pool) use ($chem) {
            $reading = $pool->visits()->where('status', 'completed')->latest('completed_at')->with('chemicalReading')->first()?->chemicalReading;
            $health = null;
            if ($reading !== null) {
                $health = $chem->getLSIStatus((float) ($reading->lsi_score ?? $chem->calculateLSI([
                    'temperature' => $reading->water_temperature, 'ph' => $reading->ph,
                    'alkalinity' => $reading->alkalinity, 'calcium_hardness' => $reading->calcium_hardness, 'salt' => $reading->salt,
                ])));
            }

            return ['id' => $pool->id, 'name' => $pool->name, 'health' => $health];
        })->all();

        $nextStop = RouteStop::query()
            ->whereIn('pool_id', $poolIds)->where('status', 'pending')
            ->whereHas('route', fn ($q) => $q->whereDate('scheduled_date', '>=', today()))
            ->with(['route:id,scheduled_date', 'pool:id,name'])
            ->get()
            ->sortBy(fn (RouteStop $s) => $s->route?->scheduled_date)
            ->first();

        $recent = ServiceVisit::query()
            ->whereIn('pool_id', $poolIds)->where('status', 'completed')->with('pool:id,name')
            ->latest('completed_at')->limit(5)->get()
            ->map(fn (ServiceVisit $v) => [
                'id' => $v->id, 'pool' => $v->pool?->getAttribute('name'), 'completed_on' => $v->completed_at?->toDateString(),
            ])->all();

        return [
            'customer_name' => $this->name($user),
            'pools' => $pools,
            'next_visit' => $nextStop !== null ? [
                'pool' => $nextStop->pool?->getAttribute('name'),
                'date' => $nextStop->route?->scheduled_date?->toDateString(),
            ] : null,
            'recent_visits' => $recent,
        ];
    }

    /** Public URL for a stored photo path, or null when unset. */
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
