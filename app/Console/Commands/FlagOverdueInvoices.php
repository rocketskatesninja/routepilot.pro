<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Daily: mark sent invoices past their due date as overdue. Runs unscoped in
 * the console, so it spans every tenant in one pass.
 */
class FlagOverdueInvoices extends Command
{
    protected $signature = 'app:flag-overdue-invoices';

    protected $description = 'Mark sent invoices past their due date as overdue.';

    public function handle(): int
    {
        // Each company by its own date — the server runs on UTC.
        $count = 0;
        Tenant::query()->where('status', 'active')->each(function (Tenant $tenant) use (&$count): void {
            $count += Invoice::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)
                ->where('status', 'sent')
                ->whereDate('due_at', '<', $tenant->today()->toDateString())
                ->update(['status' => 'overdue']);
        });

        $this->info("Flagged {$count} invoice(s) overdue.");

        return self::SUCCESS;
    }
}
