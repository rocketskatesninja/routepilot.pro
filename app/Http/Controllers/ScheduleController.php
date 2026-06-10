<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\RouteStop;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
                'agent:id,first_name,last_name',
                'stops' => fn ($q) => $q->orderBy('stop_order')->with(['pool:id,name,customer_id', 'pool.customer:id,first_name,last_name']),
            ])
            ->get()
            ->map(function (Route $route): array {
                $stops = $route->stops;

                return [
                    'id' => $route->id,
                    'agent' => $route->agent?->displayName() ?? 'Unassigned',
                    'completed' => $stops->where('status', 'completed')->count(),
                    'total' => $stops->count(),
                    'stops' => $stops->map(fn (RouteStop $s): array => [
                        'id' => $s->id,
                        'order' => $s->stop_order,
                        'pool' => $s->pool?->name,
                        'customer' => $s->pool?->customer?->displayName(),
                        'status' => $s->status,
                    ])->values()->all(),
                ];
            })->all();

        return Inertia::render('schedule/Index', [
            'date' => $date,
            'routes' => $routes,
        ]);
    }

    private function authorizeStaff(Request $request): void
    {
        $user = $request->user();
        abort_unless($user !== null && $user->tenant_id !== null && in_array($user->role, ['tenant_admin', 'agent'], true), 403);
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
