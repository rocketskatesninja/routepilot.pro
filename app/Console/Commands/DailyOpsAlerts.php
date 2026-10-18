<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\MarkMissedStops;
use App\Models\Tenant;
use App\Services\DailyOpsChecks;
use Illuminate\Console\Command;

/**
 * Daily: close out each active tenant's past days (stops nobody finished become
 * "missed"), then run the proactive operational checks and alert admins and
 * customers about anything that needs attention.
 */
class DailyOpsAlerts extends Command
{
    protected $signature = 'app:daily-ops-alerts';

    protected $description = 'Run proactive ops checks for every tenant and notify admins of anything actionable.';

    public function handle(MarkMissedStops $markMissed, DailyOpsChecks $checks): int
    {
        $total = 0;
        $missed = 0;

        Tenant::query()->where('status', 'active')->get()->each(function (Tenant $tenant) use ($markMissed, $checks, &$total, &$missed): void {
            app()->instance('tenant_id', $tenant->id);
            app()->instance('tenant', $tenant); // so Tenant::localToday() reads this tenant's timezone
            $missed += $markMissed->handle(); // before the checks, which alert on yesterday's missed stops
            $total += $checks->run($tenant->id);
        });

        $this->info("Daily ops checks complete — {$missed} stop(s) marked missed, {$total} alert(s) raised.");

        return self::SUCCESS;
    }
}
