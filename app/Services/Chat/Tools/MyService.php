<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\Customer;
use App\Models\Pool;
use App\Models\RouteStop;
use App\Models\ServiceRequest;
use App\Models\ServiceSubscription;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Chat\AssistantTool;
use Illuminate\Support\Carbon;

/**
 * A homeowner's own pools and service, as their My Pools and Services pages
 * show them: each plan, its next visit DAY (never a time), their tech's first
 * name, any pause, their price only when the company bills through RoutePilot,
 * and the last water test.
 */
class MyService extends AssistantTool
{
    public function name(): string
    {
        return 'my_service';
    }

    public function description(): string
    {
        return 'Your pools and service: each plan, how often, the next visit day, your technician, any pause or end date, '
            .'what you pay (when billed through RoutePilot), and each pool\'s last water test.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass, 'required' => []];
    }

    public function availableTo(User $user): bool
    {
        return $user->role === 'customer';
    }

    public function execute(array $params, User $user): string
    {
        $customer = Customer::query()->where('tenant_id', (int) $user->tenant_id)->where('user_id', $user->id)->first();
        if ($customer === null) {
            return 'There is no customer account linked to this login.';
        }

        $bills = $this->tenant($user)?->customer_billing !== false;
        $today = Tenant::localToday();
        $pools = $customer->pools()->with('serviceLocation')->orderBy('name')->get();
        $plans = ServiceSubscription::query()->whereIn('pool_id', $pools->pluck('id'))->current()->with('serviceType', 'agent')->get()->groupBy('pool_id');
        $next = RouteStop::query()
            ->whereIn('route_stops.service_subscription_id', $plans->flatten()->pluck('id'))
            ->where('route_stops.status', 'pending')
            ->join('routes', 'routes.id', '=', 'route_stops.route_id')
            ->where('routes.scheduled_date', '>=', $today->toDateString())
            ->groupBy('route_stops.service_subscription_id')
            ->selectRaw('route_stops.service_subscription_id as plan_id, min(routes.scheduled_date) as next_date')
            ->pluck('next_date', 'plan_id');
        $pending = ServiceRequest::query()->where('customer_id', $customer->id)->where('status', 'pending')
            ->whereNotNull('service_subscription_id')->pluck('type', 'service_subscription_id');

        if ($pools->isEmpty()) {
            return 'No pools on your account yet.';
        }

        return $pools->map(function (Pool $pool) use ($plans, $next, $pending, $bills, $today): string {
            $last = ServiceVisit::query()->where('pool_id', $pool->id)->where('status', 'completed')->latest('completed_at')->with('chemicalReading', 'agent')->first();
            $reading = $last?->chemicalReading;
            $lines = ($plans->get($pool->id) ?? collect())->map(function (ServiceSubscription $p) use ($next, $pending, $bills, $today): string {
                $held = $p->hold_ends_at !== null && $p->hold_ends_at->gte($today);

                return '  - '.($p->serviceType?->getAttribute('name') ?? 'Service').' ('.$p->scheduleLabel().')'
                    .', technician '.($p->agent?->firstName() ?? 'to be assigned')
                    .(isset($next[$p->id]) ? ', next visit '.Carbon::parse((string) $next[$p->id])->format('l, M j') : ', no visit scheduled yet')
                    .($bills ? ', $'.number_format((float) $p->price, 2).($p->frequency === 'one_time' ? '' : ' a visit') : '')
                    .($held ? ', paused '.$p->hold_starts_at?->format('M j').' – '.$p->hold_ends_at->format('M j') : '')
                    .($p->ends_on !== null ? ', ends '.$p->ends_on->format('M j, Y') : '')
                    .(isset($pending[$p->id]) ? ' (your request to '.($pending[$p->id] === 'hold' ? 'pause' : ($pending[$p->id] === 'cancel' ? 'stop' : 'change')).' it is waiting for the company)' : '');
            });

            return collect([
                "{$pool->name}: {$pool->type}, ".($pool->volume_gallons !== null ? number_format($pool->volume_gallons).' gallons' : 'size not on file').", {$pool->sanitizer_type}",
                $lines->isNotEmpty() ? "Service:\n".$lines->join("\n") : 'No current service.',
                $last !== null ? 'Last visit: '.self::link('visit', $last->id, self::day($last->completed_at, 'M j')).' by '.($last->agent?->firstName() ?? 'your technician') : 'No visits yet.',
                $reading !== null ? "Last water test: FC {$reading->free_chlorine}, pH {$reading->ph}, alkalinity {$reading->alkalinity}".($reading->cyanuric_acid !== null ? ", stabilizer {$reading->cyanuric_acid}" : '').($reading->salt !== null ? ", salt {$reading->salt}" : '') : null,
            ])->filter()->join("\n");
        })->join("\n\n");
    }
}
