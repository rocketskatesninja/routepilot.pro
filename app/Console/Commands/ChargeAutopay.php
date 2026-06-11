<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\RecordPayment;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Services\BillingService;
use App\Services\StripeService;
use Illuminate\Console\Command;

/**
 * Autopay run — charge the saved card of every autopay-enabled customer who
 * carries a balance, off-session, and settle on success. A decline leaves the
 * balance unpaid (the customer can still pay manually; richer dunning is TODO).
 */
class ChargeAutopay extends Command
{
    protected $signature = 'app:charge-autopay';

    protected $description = 'Charge saved cards for autopay-enabled customers with an outstanding balance';

    public function handle(BillingService $billing, StripeService $stripe, RecordPayment $recordPayment): int
    {
        if (! $stripe->configured()) {
            $this->warn('Stripe is not configured — skipping autopay.');

            return self::SUCCESS;
        }

        $charged = 0;
        $failed = 0;

        Tenant::query()->each(function (Tenant $tenant) use ($billing, $stripe, $recordPayment, &$charged, &$failed): void {
            app()->instance('tenant_id', $tenant->id);

            Customer::query()
                ->where('autopay_enabled', true)
                ->whereNotNull('stripe_customer_id')
                ->whereNotNull('default_payment_method_id')
                ->get()
                ->each(function (Customer $customer) use ($tenant, $billing, $stripe, $recordPayment, &$charged, &$failed): void {
                    $amount = $billing->outstandingForCustomer($customer);
                    if ($amount <= 0) {
                        return;
                    }

                    $pm = PaymentMethod::query()->find($customer->getAttribute('default_payment_method_id'));
                    if ($pm === null) {
                        return;
                    }

                    $result = $stripe->chargeOffSession(
                        (string) $customer->getAttribute('stripe_customer_id'),
                        (string) $pm->getAttribute('stripe_payment_method_id'),
                        $amount,
                        (int) $customer->id,
                        (int) $tenant->id,
                    );

                    if ($result !== null && $result['status'] === 'succeeded') {
                        $recordPayment->handle($customer, 'card', null, $result['id']);
                        $charged++;
                    } else {
                        $failed++;
                    }
                });
        });

        $this->info("Autopay: {$charged} charged, {$failed} failed.");

        return self::SUCCESS;
    }
}
