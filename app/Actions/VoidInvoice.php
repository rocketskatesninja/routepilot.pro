<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Charge;
use App\Models\Invoice;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Support\Facades\DB;

/**
 * Cancel a bill: the invoice becomes void and its charges are voided with it,
 * so the customer no longer owes them. Nothing is recorded as paid, and the
 * charges stay in the history marked voided. A bill with money paid against it
 * can't be voided (returns false); voiding twice is a no-op.
 */
class VoidInvoice
{
    public function __construct(private BillingService $billing) {}

    public function handle(Invoice $invoice, ?User $by = null): bool
    {
        if ($invoice->status === 'void') {
            return true;
        }
        if ((float) $invoice->amount_paid > 0 || $invoice->status === 'paid') {
            return false;
        }

        DB::transaction(function () use ($invoice, $by): void {
            Charge::query()->active()->where('invoice_id', $invoice->id)->update([
                'voided_at' => now(),
                'void_reason' => 'Invoice '.$invoice->number.' was voided',
                'voided_by' => $by?->id,
            ]);
            $invoice->update(['status' => 'void']);
            $this->billing->refreshInvoices($invoice->customer()->firstOrFail());
        });

        return true;
    }
}
