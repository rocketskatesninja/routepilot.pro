<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\DataRetention;
use Illuminate\Console\Command;

/**
 * Weekly housekeeping: prune old read notifications and techs' stale last-known
 * positions, and clear old website leads' IPs. Never deletes customers, reports
 * or billing records. Pass --dry to preview the counts without deleting anything.
 */
class PurgeRetention extends Command
{
    protected $signature = 'app:purge-retention {--dry : Preview the counts without deleting}';

    protected $description = 'Prune old read notifications and stale tech positions; clear old lead IPs.';

    public function handle(DataRetention $retention): int
    {
        $apply = ! $this->option('dry');
        $counts = $retention->purge($apply);

        $this->info(sprintf(
            '%sRetention: %d notification(s), %d location(s), %d lead IP(s).',
            $apply ? '' : '[dry-run] would purge — ',
            $counts['notifications_pruned'],
            $counts['agent_locations_pruned'],
            $counts['lead_ips_cleared'],
        ));

        // Record an applied run that did something as a platform compliance entry.
        if ($apply && array_sum($counts) > 0) {
            AuditLog::record(null, 'retention.purged', null, $counts);
        }

        return self::SUCCESS;
    }
}
