<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Charge;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\BillingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Bill a customer: put every charge not yet on an invoice onto a new one. The
 * invoice's totals are simply its charges (each already carries its own tax),
 * and a charge is billed once. It starts as a draft; SendInvoice emails it.
 * Returns null when there's nothing to bill.
 */
class GenerateInvoice
{
    public function __construct(private BillingService $billing) {}

    public function handle(Customer $customer): ?Invoice
    {
        // One invoice at a time per company: a double-click can't bill the same
        // charges twice, and two invoices can't take the same number.
        $lock = Cache::lock('billing:invoices:'.$customer->getAttribute('tenant_id'), 30);

        return $lock->block(10, fn (): ?Invoice => DB::transaction(function () use ($customer): ?Invoice {
            $charges = Charge::query()->active()->where('customer_id', $customer->id)->whereNull('invoice_id')->get();
            if ($charges->isEmpty()) {
                return null;
            }

            // Period/due are calendar dates in the company's time zone; issued_at is
            // a real instant. (Invoicing also runs from a command, so take the
            // time zone off the customer's company.)
            $localToday = $customer->tenant?->today() ?? Tenant::localToday();

            $invoice = Invoice::create([
                'customer_id' => $customer->id,
                'number' => $this->nextNumber(),
                'status' => 'draft',
                'period_end' => $localToday->toDateString(),
                'issued_at' => now(),
                'due_at' => $localToday->copy()->addDays(30)->toDateString(),
            ]);
            Charge::query()->whereKey($charges->pluck('id'))->update(['invoice_id' => $invoice->id]);
            $this->billing->refreshInvoices($customer); // totals, plus any credit already on account

            return $invoice->fresh();
        }));
    }

    /**
     * Next per-company sequential invoice number (the query is tenant-scoped).
     * Derived from the highest issued number, not count(), so it's robust
     * against deletes; the lock in handle() keeps two runs from reading the
     * same one, and the (tenant_id, number) unique index is the final guard.
     */
    private function nextNumber(): string
    {
        $last = Invoice::query()->orderByDesc('number')->value('number');
        $n = is_string($last) ? ((int) substr($last, 4)) + 1 : 1;

        return 'INV-'.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
