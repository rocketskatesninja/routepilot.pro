<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\GenerateInvoice;
use App\Actions\SendInvoice;
use App\Models\Tenant;
use App\Services\BillingService;
use Illuminate\Console\Command;

/**
 * Monthly: for every active company, invoice each customer who owes and has
 * charges not yet billed, and email them the invoice. A customer with no email
 * address on file gets a draft the office can print or send later.
 */
class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'app:generate-invoices';

    protected $description = 'Invoice and email customers with outstanding balances (monthly run).';

    public function handle(GenerateInvoice $generate, SendInvoice $send, BillingService $billing): int
    {
        $generated = 0;
        $emailed = 0;

        Tenant::query()->where('status', 'active')->each(function (Tenant $tenant) use ($generate, $send, $billing, &$generated, &$emailed): void {
            app()->instance('tenant_id', $tenant->id);
            foreach ($billing->outstandingBalances() as $row) {
                $invoice = $generate->handle($row['customer']);
                if ($invoice === null) {
                    continue;
                }
                $generated++;
                $emailed += $send->handle($invoice) !== null ? 1 : 0;
            }
        });

        $this->info("Generated {$generated} invoice(s), emailed {$emailed}.");

        return self::SUCCESS;
    }
}
