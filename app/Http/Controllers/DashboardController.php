<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AssembleDashboardData;
use App\Actions\AssemblePortalHome;
use App\Actions\SaveDashboardLayout;
use App\Dashboard\DashboardWidgets;
use App\Http\Requests\UpdateDashboardLayoutRequest;
use App\Http\Requests\UpdateDashboardStyleRequest;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\OnboardingStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff land on a per-user customizable widget grid: the role decides which
 * widgets are available and their default layout (App\Dashboard\DashboardWidgets),
 * and AssembleDashboardData computes only the data the placed widgets need.
 * Customers get the same grid by default, or — if they pick it — the simple
 * Home page (AssemblePortalHome): what a homeowner checks, laid out for them.
 */
class DashboardController extends Controller
{
    public function index(Request $request, AssemblePortalHome $home): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        if ($user->role === 'customer' && $user->getAttribute('dashboard_style') === 'simple') {
            $customer = Customer::query()->where('user_id', $user->id)->first();
            abort_if($customer === null, 404);

            return Inertia::render('portal/Home', $home->handle($customer));
        }

        return Inertia::render('dashboards/Grid', $this->gridData($user));
    }

    /** @return array<string, mixed> */
    private function gridData(User $user): array
    {
        $layouts = DashboardWidgets::layoutsFor($user);
        $enabled = $this->enabledKeys($layouts);
        $tenant = app()->has('tenant') ? app('tenant') : null;

        return [
            'layouts' => $layouts,
            'catalog' => DashboardWidgets::meta(),
            'palette' => DashboardWidgets::palette($user),
            'widgets' => app(AssembleDashboardData::class)->handle($user, $enabled),
            // First-run setup checklist — only the tenant admin sees it, and only
            // the client shows it while incomplete + not dismissed (see Grid.vue).
            'onboarding' => ($user->role === 'tenant_admin' && $tenant instanceof Tenant)
                ? OnboardingStatus::for($tenant)
                : null,
        ];
    }

    /** A customer switches between the widget grid and the simple Home page. */
    public function updateStyle(UpdateDashboardStyleRequest $request): RedirectResponse
    {
        $request->user()?->update(['dashboard_style' => $request->validated('style') === 'simple' ? 'simple' : null]);

        return redirect('/dashboard');
    }

    /** Hide the first-run "Getting started" checklist for this tenant. */
    public function dismissOnboarding(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $tenant = app()->has('tenant') ? app('tenant') : null;
        if ($tenant instanceof Tenant) {
            OnboardingStatus::dismiss($tenant);
        }

        return back();
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
}
