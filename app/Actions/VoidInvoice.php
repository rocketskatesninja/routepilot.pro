<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invoice;
use App\Models\ManualCharge;
use App\Models\ServiceVisit;
use Illuminate\Support\Facades\DB;

/**
 * Void (write off) an invoice: cancel the statement and release its charges from
 * the customer's balance without recording a payment (no money changed hands).
 * The source visits/charges are settled (paid_at) so they leave AR and are never
 * re-invoiced; the invoice moves to the terminal 'void' status.
 *
 * A paid invoice cannot be voided (reverse the payment first); an already-void
 * invoice is a no-op (idempotent).
 */
class VoidInvoice
{
    public function handle(Invoice $invoice): void
    {
        abort_if($invoice->status === 'paid', 422, 'A paid invoice cannot be voided.');
        if ($invoice->status === 'void') {
            return;
        }

        DB::transaction(function () use ($invoice): void {
            $invoice->loadMissing('lineItems');
            $visitIds = $invoice->lineItems->where('source_type', ServiceVisit::class)->pluck('source_id')->all();
            $chargeIds = $invoice->lineItems->where('source_type', ManualCharge::class)->pluck('source_id')->all();

            if ($visitIds !== []) {
                ServiceVisit::query()->whereKey($visitIds)->whereNull('paid_at')->update(['paid_at' => now()]);
            }
            if ($chargeIds !== []) {
                ManualCharge::query()->whereKey($chargeIds)->whereNull('paid_at')->update(['paid_at' => now()]);
            }

            $invoice->update(['status' => 'void']);
        });
    }
}
