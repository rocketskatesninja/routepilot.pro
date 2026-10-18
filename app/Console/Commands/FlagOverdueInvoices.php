<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\BillingService;
use Illuminate\Console\Command;

/**
 * Daily: bring invoices that have passed their due date up to date, for every
 * active company by its own date. The paid/overdue rule itself lives in
 * BillingService::refreshInvoices — this just runs it for the customers whose
 * bills crossed the line, so a bill with nothing left on it is never flagged.
 */
class FlagOverdueInvoices extends Command
{
    protected $signature = 'app:flag-overdue-invoices';

    protected $description = 'Mark unpaid invoices past their due date as overdue.';

    public function handle(BillingService $billing): int
    {
        $count = 0;
        Tenant::query()->where('status', 'active')->each(function (Tenant $tenant) use ($billing, &$count): void {
            app()->instance('tenant_id', $tenant->id);

            $pastDue = Invoice::query()->whereIn('status', ['draft', 'sent'])
                ->whereDate('due_at', '<', $tenant->today()->toDateString())->pluck('customer_id', 'id');
            // An archived customer's unpaid bill still goes overdue.
            Customer::withTrashed()->whereKey($pastDue->unique()->values())->each(fn (Customer $c) => $billing->refreshInvoices($c));

            $count += Invoice::query()->whereKey($pastDue->keys())->where('status', 'overdue')->count();
        });

        $this->info("Flagged {$count} invoice(s) overdue.");

        return self::SUCCESS;
    }
}
