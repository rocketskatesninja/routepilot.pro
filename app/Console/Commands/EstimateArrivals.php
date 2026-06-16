<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\EstimateArrivals as EstimateArrivalsAction;
use App\Models\Route;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Recompute drive-time ETAs (route_stops.estimated_arrival) for every upcoming,
 * not-yet-finished route — per active tenant. Runs nightly after schedules are
 * materialized so freshly created routes carry ETAs without a manual optimize.
 */
class EstimateArrivals extends Command
{
    protected $signature = 'app:estimate-arrivals';

    protected $description = 'Compute drive-time ETAs for upcoming route stops, per active tenant.';

    public function handle(EstimateArrivalsAction $estimator): int
    {
        $count = 0;

        Tenant::query()->where('status', 'active')->each(function (Tenant $tenant) use ($estimator, &$count): void {
            app()->instance('tenant_id', $tenant->id);

            Route::query()
                ->whereDate('scheduled_date', '>=', today())
                ->whereIn('status', ['draft', 'scheduled', 'in_progress'])
                ->each(function (Route $route) use ($estimator, &$count): void {
                    $estimator->handle($route);
                    $count++;
                });
        });

        $this->info("Estimated arrivals for {$count} route(s).");

        return self::SUCCESS;
    }
}
