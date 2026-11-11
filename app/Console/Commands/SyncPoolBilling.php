<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\SyncExtraPoolBilling;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Throwable;

/**
 * Hourly: keep each subscribed company's extra-pool count on its Stripe
 * subscription, so its next renewal bills the pools it has that day. Only
 * talks to Stripe when a count changed. Pass --dry to preview without changes.
 */
class SyncPoolBilling extends Command
{
    protected $signature = 'app:sync-pool-billing {--dry : Preview the changes without making them}';

    protected $description = 'Keep each company\'s extra pools on its subscription (billed at renewal).';

    public function handle(SyncExtraPoolBilling $sync): int
    {
        $changed = $failed = 0;

        foreach (Tenant::query()->whereHas('subscriptions', fn ($q) => $q->where('type', 'default'))->get() as $tenant) {
            $counts = $sync->counts($tenant);
            if ($counts === null || $counts['billed'] === $counts['extra']) {
                continue;
            }

            $line = "{$tenant->name}: {$counts['billed']} → {$counts['extra']} extra pool(s)";
            if ($this->option('dry')) {
                $this->line('[dry-run] '.$line);

                continue;
            }

            try {
                $sync->handle($tenant);
                $this->line($line);
                $changed++;
            } catch (Throwable $e) {
                // One company's Stripe trouble mustn't stop the rest; it's reported and retried next hour.
                report($e);
                $this->error("{$tenant->name}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Pool billing: {$changed} updated, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
