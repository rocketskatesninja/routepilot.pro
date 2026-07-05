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
 * Idempotent on the session's PaymentIntent: once a Payment carries that intent
 * id, the session is considered settled and is skipped — so the webhook and the
 * reconciler can both see the same session without double-recording it.
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
        if ($intent !== null && Payment::query()->where('stripe_payment_intent_id', $intent)->exists()) {
            return null; // already settled — by the webhook or an earlier reconcile
        }

        $customer = Customer::query()->find($customerId);
        if ($customer === null) {
            return null;
        }

        return $this->recordPayment->handle($customer, 'card', null, $intent);
    }
}
