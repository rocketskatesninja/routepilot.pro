<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\PaymentReceiptMail;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\BillingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Record a payment on a customer's account — any amount (partial payments are
 * fine); with no amount given, the full balance. It pays the oldest charges
 * first, and their invoices update to match. Card payments pass the amount the
 * card was actually charged, never a re-computed balance.
 */
class RecordPayment
{
    public function __construct(private BillingService $billing) {}

    public function handle(Customer $customer, string $method, ?int $userId, ?string $stripePaymentIntentId = null, ?float $amount = null): ?Payment
    {
        $payment = DB::transaction(function () use ($customer, $method, $userId, $stripePaymentIntentId, $amount): ?Payment {
            $amount = round($amount ?? $this->billing->outstandingForCustomer($customer), 2);
            if ($amount <= 0) {
                return null;
            }

            $payment = Payment::create([
                'customer_id' => $customer->id,
                'amount' => $amount,
                'status' => 'succeeded',
                'method' => $method,
                'stripe_payment_intent_id' => $stripePaymentIntentId,
                'paid_at' => now(),
                'recorded_by' => $userId,
            ]);
            $this->billing->refreshInvoices($customer);

            return $payment;
        });

        // Transactional receipt (always sent — not a marketing message).
        $email = $customer->email;
        if ($payment !== null && is_string($email) && $email !== '') {
            Mail::to($email)->queue(new PaymentReceiptMail(
                $customer->displayName(),
                (float) $payment->amount,
                $customer->tenant->name,
                now()->toFormattedDateString(),
            ));
        }

        return $payment;
    }
}
