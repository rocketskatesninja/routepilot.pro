<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\DailyOpsChecks;
use Illuminate\Console\Command;

/**
 * Daily: run the proactive operational checks for every active tenant and alert
 * admins (and, in a later slice, customers) about anything that needs attention.
 */
class DailyOpsAlerts extends Command
{
    protected $signature = 'app:daily-ops-alerts';

    protected $description = 'Run proactive ops checks for every tenant and notify admins of anything actionable.';

    public function handle(DailyOpsChecks $checks): int
    {
        $total = 0;

        Tenant::query()->where('status', 'active')->get()->each(function (Tenant $tenant) use ($checks, &$total): void {
            app()->instance('tenant_id', $tenant->id);
            $total += $checks->run($tenant->id);
        });

        $this->info("Daily ops checks complete — {$total} alert(s) raised.");

        return self::SUCCESS;
    }
}
