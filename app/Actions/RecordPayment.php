<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
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

    public function handle(Customer $customer, string $method, int $userId): ?Payment
    {
        return DB::transaction(function () use ($customer, $method, $userId): ?Payment {
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

            return Payment::create([
                'customer_id' => $customer->id,
                'amount' => $amount,
                'status' => 'succeeded',
                'method' => $method,
                'paid_at' => now(),
                'recorded_by' => $userId,
            ]);
        });
    }
}
