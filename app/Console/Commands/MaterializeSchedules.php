<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\SubscriptionMaterializer;
use Illuminate\Console\Command;

/**
 * Nightly: roll every active company's schedule forward — each by its own local
 * date, over the shared window (idempotent; the Schedule page button is the
 * manual fallback).
 */
class MaterializeSchedules extends Command
{
    protected $signature = 'app:materialize-schedules {--through= : Schedule through this date (default: the standard window)}';

    protected $description = 'Materialize route stops from active subscriptions for every tenant.';

    public function handle(SubscriptionMaterializer $materializer): int
    {
        $through = $this->option('through') ?: null;

        Tenant::query()->where('status', 'active')->get()->each(function (Tenant $tenant) use ($materializer, $through): void {
            app()->instance('tenant', $tenant);
            app()->instance('tenant_id', $tenant->id);
            $materializer->run($tenant->id, is_string($through) ? $through : null);
        });

        $this->info('Schedules updated'.(is_string($through) ? ' through '.$through : '').'.');

        return self::SUCCESS;
    }
}
