<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\BillingService;
use Illuminate\Support\Facades\DB;

/**
 * Record that one invoice was paid off-platform (cash/check): a payment for
 * what's left on it, applied to that invoice. Already paid → nothing to do.
 */
class MarkInvoicePaid
{
    public function __construct(private BillingService $billing) {}

    public function handle(Invoice $invoice, string $method, ?int $userId): void
    {
        $customer = $invoice->customer()->firstOrFail();

        DB::transaction(function () use ($invoice, $customer, $method, $userId): void {
            $this->billing->refreshInvoices($customer);
            $left = $invoice->fresh()?->balance() ?? 0.0;
            if ($left <= 0) {
                return;
            }

            Payment::create([
                'customer_id' => $customer->id,
                'invoice_id' => $invoice->id,
                'amount' => $left,
                'status' => 'succeeded',
                'method' => $method,
                'paid_at' => now(),
                'recorded_by' => $userId,
            ]);
            $this->billing->refreshInvoices($customer);
        });
    }
}
