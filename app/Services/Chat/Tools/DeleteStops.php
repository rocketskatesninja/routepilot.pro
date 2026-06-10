<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\Route;
use App\Models\RouteStop;
use App\Services\Chat\AiTool;
use Illuminate\Support\Carbon;

/**
 * Delete all future pending stops for a customer on a given weekday — used
 * to clean up orphaned stops after a service day has changed.
 */
class DeleteStops extends AiTool
{
    public function name(): string
    {
        return 'delete_stops';
    }

    public function description(): string
    {
        return 'Delete all future pending stops for a customer on a specific day of the week. '
            .'Use this to clean up orphaned stops after a service day has been changed, '
            .'or when stops on a particular day are no longer needed.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'customer_name' => ['type' => 'string', 'description' => 'The customer\'s name'],
                'pool_name' => ['type' => 'string', 'description' => 'The pool name (optional if customer has one pool)'],
                'day_of_week' => [
                    'type' => 'string',
                    'enum' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                    'description' => 'The day of the week to remove stops from',
                ],
            ],
            'required' => ['customer_name', 'day_of_week'],
        ];
    }

    /** Carbon dayOfWeek constants: 0=Sunday through 6=Saturday. */
    private const DAY_MAP = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];

    public function execute(array $params, int $tenantId): string
    {
        $customerName = trim((string) ($params['customer_name'] ?? ''));
        $poolName = trim((string) ($params['pool_name'] ?? ''));
        $day = strtolower(trim((string) ($params['day_of_week'] ?? '')));

        $targetDow = self::DAY_MAP[$day] ?? null;
        if ($targetDow === null) {
            return "Invalid day: \"{$day}\".";
        }

        $stops = RouteStop::query()
            ->where('status', 'pending')
            ->whereHas('route', fn ($q) => $q->where('tenant_id', $tenantId)->where('scheduled_date', '>=', Carbon::now()->toDateString()))
            ->whereHas('pool.customer', fn ($q) => $this->whereNameLike($q, $customerName))
            ->when($poolName !== '', fn ($q) => $q->whereHas('pool', fn ($p) => $p->where('name', 'like', "%{$poolName}%")))
            ->with('route', 'pool.customer')
            ->get();

        $toDelete = $stops->filter(fn (RouteStop $s): bool => $s->route?->scheduled_date?->dayOfWeek === $targetDow);
        $dayName = ucfirst($day);

        if ($toDelete->isEmpty()) {
            return "No pending stops found for \"{$customerName}\" on {$dayName}s.";
        }

        $count = $toDelete->count();
        $displayName = $toDelete->first()->pool?->customer?->displayName() ?? $customerName;
        $routeIds = $toDelete->pluck('route_id')->unique();

        RouteStop::query()->whereIn('id', $toDelete->pluck('id'))->delete();

        // Resequence the remaining stops on affected routes; drop empty routes.
        foreach (Route::query()->whereIn('id', $routeIds)->get() as $route) {
            $remaining = $route->stops()->orderBy('stop_order')->get()->values();
            $remaining->each(fn (RouteStop $s, int $i) => $s->update(['stop_order' => $i + 1]));
            if ($remaining->isEmpty()) {
                $route->delete();
            }
        }

        return "Done! Deleted {$count} pending ".($count === 1 ? 'stop' : 'stops')." for {$displayName} on {$dayName}s.";
    }
}
