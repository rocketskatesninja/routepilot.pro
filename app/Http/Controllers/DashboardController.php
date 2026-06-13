<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AssembleDashboardData;
use App\Actions\SaveDashboardLayout;
use App\Dashboard\DashboardWidgets;
use App\Http\Requests\UpdateDashboardLayoutRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The dashboard is a per-user customizable widget grid. Every role lands on the
 * same grid page; the role decides which widgets are available and their default
 * layout (App\Dashboard\DashboardWidgets), and AssembleDashboardData computes
 * only the data the placed widgets need.
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return Inertia::render('dashboards/Grid', $this->gridData($user));
    }

    /** @return array<string, mixed> */
    private function gridData(User $user): array
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
}
