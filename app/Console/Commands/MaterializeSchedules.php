<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\SubscriptionMaterializer;
use Illuminate\Console\Command;

/**
 * Nightly: materialize route stops from active subscriptions for every active
 * tenant (idempotent — the on-page generate is the fallback).
 */
class MaterializeSchedules extends Command
{
    protected $signature = 'app:materialize-schedules {--through= : Materialize through this date (default +2 weeks)}';

    protected $description = 'Materialize route stops from active subscriptions for every tenant.';

    public function handle(SubscriptionMaterializer $materializer): int
    {
        $through = (string) ($this->option('through') ?: now()->addWeeks(2)->toDateString());

        Tenant::query()->where('status', 'active')->get()->each(function (Tenant $tenant) use ($materializer, $through): void {
            app()->instance('tenant_id', $tenant->id);
            $materializer->run($tenant->id, $through);
        });

        $this->info('Schedules materialized through '.$through.'.');

        return self::SUCCESS;
    }
}
