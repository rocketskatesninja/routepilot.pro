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
use Illuminate\Support\Facades\Storage;
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

        $routes = Route::query()
            ->whereDate('scheduled_date', $date)
            ->with([
                'agent:id,first_name,last_name,avatar_path',
                'stops' => fn ($q) => $q->orderBy('stop_order')->with(['pool:id,name,customer_id,photo_path', 'pool.customer:id,first_name,last_name,photo_path']),
            ])
            ->get()
            ->map(function (Route $route): array {
                $stops = $route->stops;

                return [
                    'id' => $route->id,
                    'agent' => $route->agent?->displayName() ?? 'Unassigned',
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
            })->all();

        return Inertia::render('schedule/Index', [
            'date' => $date,
            'today' => Carbon::today()->toDateString(),
            'routes' => $routes,
            'canManage' => $request->user()?->role === 'tenant_admin',
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

    /** Restore a skipped stop back to pending. */
    public function unskipStop(Request $request, RouteStop $stop): RedirectResponse
    {
        abort_unless($request->user()?->role === 'tenant_admin', 403);
        abort_if($stop->route === null, 404);

        $stop->update(['status' => 'pending', 'skip_reason' => null]);

        return back()->with('success', 'Stop restored.');
    }

    private function authorizeStaff(Request $request): void
    {
        $user = $request->user();
        abort_unless($user !== null && $user->tenant_id !== null && in_array($user->role, ['tenant_admin', 'agent'], true), 403);
    }

    /** Public URL for a stored photo path, or null when unset. */
    private function photoUrl(mixed $path): ?string
    {
        return is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
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
