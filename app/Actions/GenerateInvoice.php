<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ManualCharge;
use App\Models\ServiceSubscription;
use App\Models\ServiceVisit;
use Illuminate\Support\Facades\DB;

/**
 * Assemble an invoice for a customer from their uninvoiced, unpaid items:
 * completed service visits (priced from the pool's active subscription) +
 * uninvoiced manual charges, plus the tenant's sales tax on taxable lines.
 * The billed items are captured (invoice_id) so they can't be billed twice.
 * Returns null when there is nothing to invoice. (Stripe settlement layers on
 * top later; this is the document/AR layer.)
 */
class GenerateInvoice
{
    public function handle(Customer $customer): ?Invoice
    {
        return DB::transaction(function () use ($customer): ?Invoice {
            $poolIds = $customer->pools()->pluck('id');

            $visits = ServiceVisit::query()
                ->whereIn('pool_id', $poolIds)
                ->where('status', 'completed')
                ->whereNull('paid_at')->whereNull('invoice_id')
                ->with('pool.subscriptions.serviceType')
                ->get();

            $charges = ManualCharge::query()
                ->where('customer_id', $customer->id)
                ->whereNull('paid_at')->whereNull('invoice_id')
                ->get();

            if ($visits->isEmpty() && $charges->isEmpty()) {
                return null;
            }

            $taxRate = (float) ($customer->tenant?->getAttribute('tax_rate') ?? 0.0);

            // Period/due are calendar dates in the tenant's timezone; issued_at is
            // a real instant and stays UTC. (Invoicing also runs from a command
            // that binds only tenant_id, so take the tz off the customer's tenant.)
            $localToday = $customer->tenant?->today() ?? now();

            $invoice = Invoice::create([
                'customer_id' => $customer->id,
                'number' => $this->nextNumber(),
                'status' => 'sent',
                'period_end' => $localToday->toDateString(),
                'issued_at' => now(),
                'due_at' => $localToday->copy()->addDays(30)->toDateString(),
            ]);

            $subtotal = 0.0;
            $taxableBase = 0.0;

            foreach ($visits as $visit) {
                $price = $this->visitPrice($visit);
                $invoice->lineItems()->create([
                    'type' => 'service',
                    'description' => 'Pool service — '.$visit->pool->name.($visit->completed_at !== null ? ' ('.$visit->completed_at->toDateString().')' : ''),
                    'quantity' => 1,
                    'unit_price' => $price,
                    'amount' => $price,
                    'taxable' => false,
                    'source_type' => ServiceVisit::class,
                    'source_id' => $visit->id,
                ]);
                $subtotal += $price;
            }

            foreach ($charges as $charge) {
                $amount = (float) $charge->amount;
                $taxable = (bool) $charge->taxable;
                $invoice->lineItems()->create([
                    'type' => 'manual',
                    'description' => $charge->description,
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'amount' => $amount,
                    'taxable' => $taxable,
                    'source_type' => ManualCharge::class,
                    'source_id' => $charge->id,
                ]);
                $subtotal += $amount;
                if ($taxable) {
                    $taxableBase += $amount;
                }
            }

            $tax = round($taxableBase * $taxRate, 2);
            $invoice->update([
                'subtotal' => round($subtotal, 2),
                'tax' => $tax,
                'total' => round($subtotal + $tax, 2),
            ]);

            ServiceVisit::query()->whereKey($visits->pluck('id')->all())->update(['invoice_id' => $invoice->id]);
            ManualCharge::query()->whereKey($charges->pluck('id')->all())->update(['invoice_id' => $invoice->id]);

            return $invoice;
        });
    }

    private function visitPrice(ServiceVisit $visit): float
    {
        $sub = $visit->pool?->subscriptions->firstWhere('status', 'active');

        return $sub instanceof ServiceSubscription ? (float) $sub->serviceType->price : 0.0;
    }

    /**
     * Next per-tenant sequential invoice number (the query is tenant-scoped).
     * Derived from the highest issued number, not count(): robust against
     * deletes, and within a batch transaction the prior inserts are visible to
     * this read. The (tenant_id, number) unique index is the final guard.
     */
    private function nextNumber(): string
    {
        $last = Invoice::query()->orderByDesc('number')->value('number');
        $n = is_string($last) ? ((int) substr($last, 4)) + 1 : 1;

        return 'INV-'.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
