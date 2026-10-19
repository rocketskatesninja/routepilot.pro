<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\Pool;
use App\Models\RouteStop;
use App\Models\ServiceRequest;
use App\Models\ServiceSubscription;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Services\BillingService;
use App\Services\StripeService;
use App\Support\PortalVisit;

/**
 * The customer's Home page: when someone is coming next, how the water looked,
 * what they owe, and the latest visit report — everything a homeowner checks.
 */
class AssemblePortalHome
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly StripeService $stripe,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Customer $customer): array
    {
        $pools = $customer->pools()->orderBy('name')->get();
        $poolIds = $pools->pluck('id');

        $latest = ServiceVisit::query()->whereIn('pool_id', $poolIds)->where('status', 'completed')
            ->with(PortalVisit::WITH)->latest('completed_at')->first();

        return [
            'firstName' => $customer->first_name,
            // With more than one pool, the page says which pool each thing is about.
            'poolCount' => $pools->count(),
            'upcoming' => $this->upcoming($poolIds->all()),
            'hasService' => ServiceSubscription::query()->whereIn('pool_id', $poolIds)->whereIn('status', ['active', 'paused'])->exists(),
            'lastVisit' => $latest !== null ? PortalVisit::toArray($latest) : null,
            'water' => $pools->map(fn (Pool $pool): ?array => $this->water($pool))->filter()->values()->all(),
            'balance' => $this->billing->outstandingForCustomer($customer),
            'autopay' => (bool) $customer->getAttribute('autopay_enabled'),
            'canPay' => $this->stripe->configured(),
            'pendingRequests' => ServiceRequest::query()->where('customer_id', $customer->id)->where('status', 'pending')->count(),
            'today' => Tenant::localToday()->toDateString(),
        ];
    }

    /**
     * The next two days with a visit: who's coming, roughly when, and for what.
     *
     * @param  list<int>  $poolIds
     * @return list<array<string, mixed>>
     */
    private function upcoming(array $poolIds): array
    {
        if ($poolIds === []) {
            return [];
        }

        // The pool filter keeps this to the customer's own stops.
        $stops = RouteStop::query()->select('route_stops.*')
            ->join('routes', 'routes.id', '=', 'route_stops.route_id')
            ->whereIn('route_stops.pool_id', $poolIds)
            ->where('route_stops.status', 'pending')
            ->whereDate('routes.scheduled_date', '>=', Tenant::localToday())
            ->with(['route:id,scheduled_date,agent_id', 'route.agent:id,first_name,last_name', 'serviceSubscription.serviceType:id,name', 'pool:id,name'])
            ->orderBy('routes.scheduled_date')->orderBy('route_stops.stop_order')
            ->limit(20)->get();

        return $stops->groupBy(fn (RouteStop $s): string => (string) $s->route?->scheduled_date?->toDateString())
            ->take(2)
            ->map(function ($day, string $date): array {
                $first = $day->first();

                return [
                    'date' => $date,
                    'agent' => $first?->route?->agent?->firstName(),
                    'services' => $day->map(fn (RouteStop $s): string => $s->serviceSubscription->serviceType->name ?? 'a service visit')->unique()->values()->all(),
                    'pools' => $day->map(fn (RouteStop $s): ?string => $s->pool?->getAttribute('name'))->filter()->unique()->values()->all(),
                ];
            })->values()->all();
    }

    /** @return array<string, mixed>|null */
    private function water(Pool $pool): ?array
    {
        $visit = $pool->visits()->where('status', 'completed')->whereHas('chemicalReading')
            ->with('chemicalReading')->latest('completed_at')->first();

        return $visit === null ? null : [
            'pool' => $pool->getAttribute('name'),
            'on' => Tenant::localDate($visit->completed_at),
            'reading' => PortalVisit::reading($visit),
            'targets' => $pool->custom_target_ranges,
        ];
    }
}
