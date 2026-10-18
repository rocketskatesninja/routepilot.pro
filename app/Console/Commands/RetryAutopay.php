<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ChargeAutopayCustomer;
use App\Models\Customer;
use App\Models\Tenant;
use App\Services\StripeService;
use Illuminate\Console\Command;

/**
 * Daily dunning retry — re-attempt autopay for customers whose card was declined
 * this month and who still owe, once a day, until the month's third decline.
 * Then it gives up; the account stays owing for the office to handle.
 */
class RetryAutopay extends Command
{
    protected $signature = 'app:retry-autopay';

    protected $description = 'Retry this month\'s declined autopay charges (up to 3 attempts, a day apart)';

    public function handle(ChargeAutopayCustomer $charger, StripeService $stripe): int
    {
        if (! $stripe->configured()) {
            return self::SUCCESS;
        }

        $retried = 0;
        $recovered = 0;

        Tenant::query()->where('status', 'active')->each(function (Tenant $tenant) use ($charger, &$retried, &$recovered): void {
            app()->instance('tenant_id', $tenant->id);

            Customer::query()
                ->where('autopay_enabled', true)
                ->whereNotNull('stripe_customer_id')
                ->whereNotNull('default_payment_method_id')
                ->each(function (Customer $customer) use ($charger, &$retried, &$recovered): void {
                    $declines = $charger->declinesThisMonth($customer);
                    // Not in dunning, out of attempts, or declined less than a day
                    // ago (the run on the 2nd is an hour before this one, and a
                    // same-day retry would only replay that decline).
                    if ($declines->isEmpty() || $declines->count() >= ChargeAutopayCustomer::MAX_ATTEMPTS
                        || $declines->first()->created_at?->gt(now()->subHours(20))) {
                        return;
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
