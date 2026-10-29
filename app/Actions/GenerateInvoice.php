<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Charge;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\BillingService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
                'number' => $this->numberFor($customer, $localToday),
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
     * The customer's last name and the invoice date, e.g. "RODRIGUEZ-20261101";
     * another one with the same name that day gets "-2", "-3"… (the query is
     * tenant-scoped). The lock in handle() keeps two runs from taking the same
     * number, and the (tenant_id, number) unique index is the final guard.
     * Invoices made before this kept their "INV-00001" numbers.
     */
    private function numberFor(Customer $customer, CarbonInterface $date): string
    {
        $name = collect([$customer->last_name, $customer->first_name])
            ->map(fn ($n) => substr((string) preg_replace('/[^A-Z0-9]/', '', strtoupper(Str::ascii((string) $n))), 0, 20))
            ->first(fn (string $n) => $n !== '') ?? 'INVOICE';
        $base = $name.'-'.$date->format('Ymd');

        $taken = Invoice::query()->where('number', 'like', $base.'%')->pluck('number')->all();
        $number = $base;
        for ($n = 2; in_array($number, $taken, true); $n++) {
            $number = $base.'-'.$n;
        }

        return $number;
    }
}
