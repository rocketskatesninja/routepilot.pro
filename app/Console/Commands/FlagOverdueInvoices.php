<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Invoice;
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
        $count = Invoice::query()
            ->where('status', 'sent')
            ->whereDate('due_at', '<', today())
            ->update(['status' => 'overdue']);

        $this->info("Flagged {$count} invoice(s) overdue.");

        return self::SUCCESS;
    }
}
