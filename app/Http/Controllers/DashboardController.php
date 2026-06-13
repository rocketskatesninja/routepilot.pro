<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AssembleDashboardData;
use App\Actions\SaveDashboardLayout;
use App\Dashboard\DashboardWidgets;
use App\Http\Requests\UpdateDashboardLayoutRequest;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ChemistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            default => Inertia::render('dashboards/Admin', $this->admin($user)),
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
                'logo' => $this->photoUrl($t->getAttribute('logo_path')),
            ])->all(),
        ];
    }

    /**
     * The tenant_admin command-center: a per-user customizable widget grid. The
     * saved layout (or the catalog default) decides which widgets are placed;
     * AssembleDashboardData computes only the data those widgets need.
     *
     * @return array<string, mixed>
     */
    private function admin(User $user): array
    {
        $layouts = DashboardWidgets::layoutsFor($user);
        $enabled = $this->enabledKeys($layouts);

        return [
            'layouts' => $layouts,
            'catalog' => DashboardWidgets::meta(),
            'palette' => DashboardWidgets::palette($user),
            'widgets' => app(AssembleDashboardData::class)->handle($user, $enabled),
        ];
    }

    /**
     * The union of widget keys across the desktop + mobile layouts — the data to
     * compute, so whichever layout renders has what it needs.
     *
     * @param  array{desktop: list<array<string, int|string>>, mobile: list<array<string, int|string>>}  $layouts
     * @return list<string>
     */
    private function enabledKeys(array $layouts): array
    {
        $keys = [];
        foreach ([...$layouts['desktop'], ...$layouts['mobile']] as $item) {
            $i = $item['i'] ?? null;
            if (is_string($i)) {
                $keys[$i] = true;
            }
        }

        return array_keys($keys);
    }

    /** Persist the acting user's customized dashboard layout for one mode. */
    public function saveLayout(UpdateDashboardLayoutRequest $request, SaveDashboardLayout $action): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $layout = $request->validated('layout');
        $mode = $request->validated('mode');
        $action->handle($user, is_string($mode) ? $mode : 'desktop', is_array($layout) ? $layout : []);

        return back();
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

            return ['id' => $pool->id, 'name' => $pool->name, 'photo' => $this->photoUrl($pool->getAttribute('photo_path')), 'health' => $health];
        })->all();

        $nextStop = RouteStop::query()
            ->whereIn('pool_id', $poolIds)->where('status', 'pending')
            ->whereHas('route', fn ($q) => $q->whereDate('scheduled_date', '>=', today()))
            ->with(['route:id,scheduled_date', 'pool:id,name'])
            ->get()
            ->sortBy(fn (RouteStop $s) => $s->route?->scheduled_date)
            ->first();

        $recent = ServiceVisit::query()
            ->whereIn('pool_id', $poolIds)->where('status', 'completed')->with('pool:id,name,photo_path')
            ->latest('completed_at')->limit(5)->get()
            ->map(fn (ServiceVisit $v) => [
                'id' => $v->id, 'pool' => $v->pool?->getAttribute('name'),
                'pool_photo' => $this->photoUrl($v->pool?->getAttribute('photo_path')), 'completed_on' => $v->completed_at?->toDateString(),
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

    private function name(?User $user): string
    {
        if ($user === null) {
            return '—';
        }
        $name = trim((string) $user->getAttribute('first_name').' '.(string) $user->getAttribute('last_name'));

        return $name !== '' ? $name : '—';
    }
}
