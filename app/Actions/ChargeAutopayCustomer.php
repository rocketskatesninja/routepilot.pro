<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\PaymentFailedMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Notifications\AutopayDeclined;
use App\Services\BillingService;
use App\Services\StripeService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Charge one autopay customer's saved card off-session. On success, settle (via
 * RecordPayment). On decline, run dunning: record a failed Payment, flag open
 * invoices overdue, and — on the FIRST recent failure only (no spam on retries)
 * — email the customer a pay link and alert the tenant admins.
 */
class ChargeAutopayCustomer
{
    public function __construct(
        private BillingService $billing,
        private StripeService $stripe,
        private RecordPayment $recordPayment,
    ) {}

    /** @return 'charged'|'declined'|'skipped' */
    public function handle(Customer $customer): string
    {
        $amount = $this->billing->outstandingForCustomer($customer);
        if ($amount <= 0) {
            return 'skipped';
        }

        $stripeCustomer = $customer->getAttribute('stripe_customer_id');
        $pm = PaymentMethod::query()->find($customer->getAttribute('default_payment_method_id'));
        if (! is_string($stripeCustomer) || $pm === null) {
            return 'skipped';
        }

        $result = $this->stripe->chargeOffSession(
            $stripeCustomer,
            (string) $pm->getAttribute('stripe_payment_method_id'),
            $amount,
            (int) $customer->id,
            (int) $customer->getAttribute('tenant_id'),
            $this->stripe->connectAccountFor($customer),
        );

        if ($result !== null && $result['status'] === 'succeeded') {
            $this->recordPayment->handle($customer, 'card', null, $result['id']);

            return 'charged';
        }

        $this->dun($customer, $amount, $result['id'] ?? null);

        return 'declined';
    }

    private function dun(Customer $customer, float $amount, ?string $paymentIntentId): void
    {
        $firstFailure = Payment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(5))
            ->doesntExist();

        Payment::create([
            'customer_id' => $customer->id,
            'amount' => $amount,
            'status' => 'failed',
            'method' => 'card',
            'stripe_payment_intent_id' => $paymentIntentId,
            'failure_reason' => 'Autopay charge declined',
        ]);

        Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['draft', 'sent'])
            ->update(['status' => 'overdue']);

        if (! $firstFailure) {
            return; // already alerted this cycle — don't spam on retries
        }

        $email = $customer->email;
        if (is_string($email) && $email !== '') {
            $payUrl = URL::signedRoute('pay.link', ['customer' => $customer->id]);
            Mail::to($email)->queue(new PaymentFailedMail($customer->displayName(), $amount, $customer->tenant->name, $payUrl));
        }

        $admins = User::query()
            ->where('tenant_id', $customer->getAttribute('tenant_id'))
            ->where('role', 'tenant_admin')->where('is_active', true)
            ->get();
        Notification::send($admins, new AutopayDeclined($customer, $amount));
    }
}
