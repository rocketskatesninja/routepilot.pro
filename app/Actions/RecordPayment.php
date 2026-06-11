<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ManualCharge;
use App\Models\Payment;
use App\Models\ServiceVisit;
use App\Services\BillingService;
use Illuminate\Support\Facades\DB;

/**
 * Record a full-balance payment: settle the customer's unpaid completed
 * visits + uninvoiced manual charges, and write a Payment for the total.
 * (Partial payments + Stripe settlement are the Phase-6 path.)
 */
class RecordPayment
{
    public function __construct(private BillingService $billing) {}

    public function handle(Customer $customer, string $method, ?int $userId, ?string $stripePaymentIntentId = null): ?Payment
    {
        return DB::transaction(function () use ($customer, $method, $userId, $stripePaymentIntentId): ?Payment {
            $amount = $this->billing->outstandingForCustomer($customer);
            if ($amount <= 0) {
                return null;
            }

            ServiceVisit::query()
                ->whereIn('pool_id', $customer->pools()->pluck('id'))
                ->where('status', 'completed')
                ->whereNull('paid_at')
                ->update(['paid_at' => now()]);

            ManualCharge::query()
                ->where('customer_id', $customer->id)
                ->whereNull('paid_at')
                ->update(['paid_at' => now()]);

            // A full-balance payment clears any open invoices too.
            Invoice::query()
                ->where('customer_id', $customer->id)
                ->whereIn('status', ['draft', 'sent', 'overdue'])
                ->get()
                ->each(fn (Invoice $invoice) => $invoice->update(['amount_paid' => $invoice->total, 'status' => 'paid']));

            return Payment::create([
                'customer_id' => $customer->id,
                'amount' => $amount,
                'status' => 'succeeded',
                'method' => $method,
                'stripe_payment_intent_id' => $stripePaymentIntentId,
                'paid_at' => now(),
                'recorded_by' => $userId,
            ]);
        });
    }
}
