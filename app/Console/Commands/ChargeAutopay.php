<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ChargeAutopayCustomer;
use App\Models\Customer;
use App\Models\Tenant;
use App\Services\StripeService;
use Illuminate\Console\Command;

/**
 * Autopay run — charge the saved card of every autopay-enabled customer who
 * carries a balance (off-session) and settle on success. Declines are dunned
 * by ChargeAutopayCustomer and retried by app:retry-autopay.
 */
class ChargeAutopay extends Command
{
    protected $signature = 'app:charge-autopay';

    protected $description = 'Charge saved cards for autopay-enabled customers with an outstanding balance';

    public function handle(ChargeAutopayCustomer $charger, StripeService $stripe): int
    {
        if (! $stripe->configured()) {
            $this->warn('Stripe is not configured — skipping autopay.');

            return self::SUCCESS;
        }

        $charged = 0;
        $declined = 0;

        Tenant::query()->each(function (Tenant $tenant) use ($charger, &$charged, &$declined): void {
            app()->instance('tenant_id', $tenant->id);

            Customer::query()
                ->where('autopay_enabled', true)
                ->whereNotNull('stripe_customer_id')
                ->whereNotNull('default_payment_method_id')
                ->get()
                ->each(function (Customer $customer) use ($charger, &$charged, &$declined): void {
                    $result = $charger->handle($customer);
                    if ($result === 'charged') {
                        $charged++;
                    } elseif ($result === 'declined') {
                        $declined++;
                    }
                });
        });

        $this->info("Autopay: {$charged} charged, {$declined} declined.");

        return self::SUCCESS;
    }
}
