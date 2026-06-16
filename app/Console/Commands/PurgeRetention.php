<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\DataRetention;
use Illuminate\Console\Command;

/**
 * Platform-wide data-retention sweep: finalize expired customer erasures
 * (GDPR right-to-be-forgotten) and prune stale read notifications. Pass --dry
 * to preview the counts without deleting anything.
 */
class PurgeRetention extends Command
{
    protected $signature = 'app:purge-retention {--dry : Preview the counts without deleting}';

    protected $description = 'Hard-delete expired soft-deleted customer PII (GDPR erasure) and prune old read notifications.';

    public function handle(DataRetention $retention): int
    {
        $apply = ! $this->option('dry');
        $counts = $retention->purge($apply);

        $this->info(sprintf(
            '%sRetention: %d customer(s), %d photo file(s), %d notification(s).',
            $apply ? '' : '[dry-run] would purge — ',
            $counts['customers_purged'],
            $counts['photo_files_deleted'],
            $counts['notifications_pruned'],
        ));

        // Record an applied run that did something as a platform compliance entry.
        if ($apply && array_sum($counts) > 0) {
            AuditLog::record(null, 'retention.purged', null, $counts);
        }

        return self::SUCCESS;
    }
}
