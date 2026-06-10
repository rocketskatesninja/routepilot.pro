<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\GenerateInvoice;
use App\Models\Tenant;
use App\Services\BillingService;
use Illuminate\Console\Command;

/**
 * Monthly: invoice every customer who has uninvoiced outstanding items, for
 * every active tenant.
 */
class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'app:generate-invoices';

    protected $description = 'Generate invoices for customers with outstanding balances (monthly run).';

    public function handle(GenerateInvoice $action, BillingService $billing): int
    {
        $generated = 0;

        Tenant::query()->where('status', 'active')->get()->each(function (Tenant $tenant) use ($action, $billing, &$generated): void {
            app()->instance('tenant_id', $tenant->id);
            foreach ($billing->outstandingBalances() as $row) {
                if ($action->handle($row['customer']) !== null) {
                    $generated++;
                }
            }
        });

        $this->info("Generated {$generated} invoice(s).");

        return self::SUCCESS;
    }
}
