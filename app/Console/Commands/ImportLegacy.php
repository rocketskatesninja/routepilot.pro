<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LegacyImporter;
use Illuminate\Console\Command;

/**
 * One-time cutover of a legacy tenant's data into a fresh tenant on the live DB.
 * Reads from the `legacy` connection (a restored old-DB dump). Always preview
 * with --dry first; --fresh re-imports over a previously imported tenant.
 */
class ImportLegacy extends Command
{
    protected $signature = 'app:import-legacy
        {--source=legacy : The DB connection holding the old data}
        {--old-slug=gpc : The source tenant slug to import}
        {--slug=gpc : The target tenant slug to create}
        {--name=Acme Pool Co : The target tenant name}
        {--dry : Run the full import in a transaction and roll back (preview only)}
        {--fresh : Wipe a previously imported target tenant and re-import}';

    protected $description = 'Cut over a legacy tenant (Acme Pool Co) into a fresh tenant on the live database.';

    public function handle(LegacyImporter $importer): int
    {
        $opts = [
            'source' => (string) $this->option('source'),
            'old_slug' => (string) $this->option('old-slug'),
            'slug' => (string) $this->option('slug'),
            'name' => (string) $this->option('name'),
            'dry' => (bool) $this->option('dry'),
            'fresh' => (bool) $this->option('fresh'),
        ];

        $result = $importer->run($opts);

        if (($result['skipped'] ?? false) === true) {
            $this->warn("Already imported (tenant #{$result['tenant_id']}). Use --fresh to re-import.");

            return self::SUCCESS;
        }

        $this->info(($result['dry'] ?? false) ? '[dry run — rolled back] would import:' : 'Imported into the live DB:');
        $this->table(['record', 'count'], collect($result)
            ->except(['dry', 'tenant_id'])
            ->map(fn ($v, $k): array => [$k, (string) $v])
            ->values()
            ->all());
        $this->line("Target tenant id: {$result['tenant_id']}");

        return self::SUCCESS;
    }
}
