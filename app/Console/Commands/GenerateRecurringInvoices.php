<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RecurringBillingProfile;

class GenerateRecurringInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recurring:generate-invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate invoices for all active recurring billing profiles that are due.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $profiles = RecurringBillingProfile::active()->get();
        $count = 0;
        foreach ($profiles as $profile) {
            if ($profile->shouldGenerateInvoice()) {
                $invoice = $profile->generateInvoice();
                $this->info("Generated invoice #{$invoice->invoice_number} for profile #{$profile->id} ({$profile->name})");
                $count++;
            }
        }
        $this->info("Total invoices generated: $count");
        return 0;
    }
}
