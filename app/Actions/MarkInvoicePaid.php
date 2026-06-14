<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invoice;
use App\Models\ManualCharge;
use App\Models\Payment;
use App\Models\ServiceVisit;
use Illuminate\Support\Facades\DB;

/**
 * Settle a single invoice that was paid off-platform (cash/check): mark its
 * source visits + manual charges paid so the customer's running balance drops,
 * set the invoice paid, and write a Payment for the outstanding amount. A
 * paid invoice is a no-op (idempotent).
 */
class MarkInvoicePaid
{
    public function handle(Invoice $invoice, string $method, ?int $userId): void
    {
        if ($invoice->status === 'paid') {
            return;
        }

        DB::transaction(function () use ($invoice, $method, $userId): void {
            $invoice->loadMissing('lineItems');
            $visitIds = $invoice->lineItems->where('source_type', ServiceVisit::class)->pluck('source_id')->all();
            $chargeIds = $invoice->lineItems->where('source_type', ManualCharge::class)->pluck('source_id')->all();

            if ($visitIds !== []) {
                ServiceVisit::query()->whereKey($visitIds)->whereNull('paid_at')->update(['paid_at' => now()]);
            }
            if ($chargeIds !== []) {
                ManualCharge::query()->whereKey($chargeIds)->whereNull('paid_at')->update(['paid_at' => now()]);
            }

            $balance = $invoice->balance();
            $invoice->update(['amount_paid' => $invoice->total, 'status' => 'paid']);

            if ($balance > 0) {
                Payment::create([
                    'customer_id' => $invoice->getAttribute('customer_id'),
                    'invoice_id' => $invoice->id,
                    'amount' => $balance,
                    'status' => 'succeeded',
                    'method' => $method,
                    'paid_at' => now(),
                    'recorded_by' => $userId,
                ]);
            }
        });
    }
}
