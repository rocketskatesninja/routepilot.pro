<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\PaymentReceiptMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\BillingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Record a payment on a customer's account — the one way money comes in, from
 * the office (cash/check), a card checkout, or autopay. Any amount is fine
 * (partial payments too); with no amount given, the full balance — or, against
 * an invoice, what's left on that invoice. A payment against an invoice pays
 * that invoice first; any other pays the oldest charges first, and the invoices
 * update to match. Card payments pass the amount the card was actually charged,
 * never a re-computed balance. A receipt is emailed.
 */
class RecordPayment
{
    public function __construct(private BillingService $billing) {}

    public function handle(Customer $customer, string $method, ?int $userId, ?string $stripePaymentIntentId = null, ?float $amount = null, ?Invoice $invoice = null): ?Payment
    {
        // One payment at a time per customer, so a double-click — or the webhook
        // and the reconciler settling the same checkout at once — can't record
        // the same money twice.
        $payment = Cache::lock("billing:customer:{$customer->id}", 30)->block(10, fn (): ?Payment => DB::transaction(function () use ($customer, $method, $userId, $stripePaymentIntentId, $amount, $invoice): ?Payment {
            if ($stripePaymentIntentId !== null && Payment::query()->where('stripe_payment_intent_id', $stripePaymentIntentId)->where('status', 'succeeded')->exists()) {
                return null; // this card payment is already on the books
            }

            $amount = round($amount ?? $this->owed($customer, $invoice), 2);
            if ($amount <= 0) {
                return null;
            }

            $payment = Payment::create([
                'customer_id' => $customer->id,
                'invoice_id' => $invoice?->id,
                'amount' => $amount,
                'status' => 'succeeded',
                'method' => $method,
                'stripe_payment_intent_id' => $stripePaymentIntentId,
                'paid_at' => now(),
                'recorded_by' => $userId,
            ]);
            $this->billing->refreshInvoices($customer);

            return $payment;
        }));

        // Transactional receipt (always sent — not a marketing message).
        $email = $customer->email;
        if ($payment !== null && is_string($email) && $email !== '') {
            Mail::to($email)->queue(new PaymentReceiptMail(
                $customer->first_name ?: $customer->displayName(),
                (float) $payment->amount,
                $customer->tenant->name,
                $customer->tenant->today()->toFormattedDateString(),
                $customer->tenant,
            ));
        }

        return $payment;
    }

    /** What's owed: the customer's whole balance, or what's left on one invoice. */
    private function owed(Customer $customer, ?Invoice $invoice): float
    {
        if ($invoice === null) {
            return $this->billing->outstandingForCustomer($customer);
        }

        $this->billing->refreshInvoices($customer);

        return $invoice->refresh()->balance();
    }
}
