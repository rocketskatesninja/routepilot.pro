<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ChargeAutopayCustomer;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\StripeService;
use Illuminate\Console\Command;

/**
 * Daily dunning retry — re-attempt autopay for customers with a recent decline
 * (1–2 failures in the last 5 days) and an outstanding balance. After 3 failed
 * attempts it gives up; the account stays overdue for the tenant to handle.
 */
class RetryAutopay extends Command
{
    protected $signature = 'app:retry-autopay';

    protected $description = 'Retry recently-declined autopay charges (capped at 3 attempts over a few days)';

    public function handle(ChargeAutopayCustomer $charger, StripeService $stripe): int
    {
        if (! $stripe->configured()) {
            return self::SUCCESS;
        }

        $retried = 0;
        $recovered = 0;

        Tenant::query()->each(function (Tenant $tenant) use ($charger, &$retried, &$recovered): void {
            app()->instance('tenant_id', $tenant->id);

            Customer::query()
                ->where('autopay_enabled', true)
                ->whereNotNull('stripe_customer_id')
                ->whereNotNull('default_payment_method_id')
                ->get()
                ->each(function (Customer $customer) use ($charger, &$retried, &$recovered): void {
                    $failures = Payment::query()
                        ->where('customer_id', $customer->id)
                        ->where('status', 'failed')
                        ->where('created_at', '>=', now()->subDays(5))
                        ->count();

                    if ($failures === 0 || $failures >= 3) {
                        return; // not in a dunning window, or already gave up
                    }

                    $retried++;
                    if ($charger->handle($customer) === 'charged') {
                        $recovered++;
                    }
                });
        });

        $this->info("Autopay retry: {$retried} retried, {$recovered} recovered.");

        return self::SUCCESS;
    }
}
