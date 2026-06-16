<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\VisitEtaUpdated;
use App\Models\Route;
use App\Support\EtaWindow;

/**
 * On an agent's live GPS ping, re-time their remaining route from where they
 * actually are, then push the fresh "on-my-way" window to the customer whose
 * pool is the next pending stop (if they have a portal login listening).
 */
class ReanchorEtas
{
    public function __construct(private EstimateArrivals $arrivals) {}

    public function handle(Route $route, float $lat, float $lng): void
    {
        $this->arrivals->handle($route, [$lat, $lng], now());

        $next = $route->stops()
            ->where('status', 'pending')
            ->orderBy('stop_order')
            ->with(['pool.customer.user'])
            ->first();

        $customer = $next?->pool?->customer;
        if ($next === null || $customer === null || $customer->user === null) {
            return; // no next stop, or no portal user is listening
        }

        $window = EtaWindow::for($next->estimated_arrival);
        if ($window === null) {
            return;
        }

        event(new VisitEtaUpdated(
            (int) $customer->getKey(),
            $window,
            $next->pool->name,
            $route->scheduled_date->toDateString(),
        ));
    }
}
