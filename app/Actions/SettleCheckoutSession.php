<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\Payment;

/**
 * Settle a paid Stripe Checkout session against the customer's balance. The
 * single settlement path shared by the webhook (checkout.session.completed)
 * and the reconciliation job that sweeps up sessions a webhook never delivered.
 *
 * Idempotent on the session's PaymentIntent: RecordPayment skips an intent
 * that's already on the books (under a per-customer lock), so the webhook and
 * the reconciler can both see the same session — even at the same moment —
 * without double-recording it.
 */
class SettleCheckoutSession
{
    public function __construct(private RecordPayment $recordPayment) {}

    /**
     * @param  array<string, mixed>  $session  a Checkout Session object from Stripe
     */
    public function handle(array $session): ?Payment
    {
        if (($session['payment_status'] ?? null) !== 'paid') {
            return null;
        }

        $meta = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $tenantId = (int) ($meta['tenant_id'] ?? 0);
        $customerId = (int) ($meta['customer_id'] ?? 0);
        if ($tenantId <= 0 || $customerId <= 0) {
            return null;
        }

        app()->instance('tenant_id', $tenantId);

        $intent = is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : null;
        $customer = Customer::query()->find($customerId);
        if ($customer === null) {
            return null;
        }

        // Record what Stripe actually collected — the balance may have changed since checkout opened.
        $collected = is_numeric($session['amount_total'] ?? null) ? ((int) $session['amount_total']) / 100 : null;

        return $this->recordPayment->handle($customer, 'card', null, $intent, $collected);
    }
}
