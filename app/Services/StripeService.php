<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Http;

/**
 * Thin Stripe gateway over the HTTP API (no SDK dependency; Http::fake()-able).
 * Reads the secret key from config('services.stripe.*'). Returns null when
 * Stripe isn't configured so callers can degrade gracefully.
 */
class StripeService
{
    public function configured(): bool
    {
        return (string) config('services.stripe.secret') !== '';
    }

    /**
     * Create a Stripe Checkout session for a one-off balance payment.
     * Customer + tenant are stamped into metadata so the webhook can settle.
     */
    public function createBalanceCheckout(Customer $customer, float $amount, string $successUrl, string $cancelUrl): ?string
    {
        $secret = (string) config('services.stripe.secret');
        if ($secret === '' || $amount <= 0) {
            return null;
        }

        $response = Http::asForm()->withToken($secret)->post('https://api.stripe.com/v1/checkout/sessions', [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'customer_email' => $customer->email,
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => 'usd',
            'line_items[0][price_data][unit_amount]' => (int) round($amount * 100),
            'line_items[0][price_data][product_data][name]' => 'Account balance payment',
            'metadata[customer_id]' => (string) $customer->id,
            'metadata[tenant_id]' => (string) $customer->getAttribute('tenant_id'),
        ]);

        if (! $response->successful()) {
            return null;
        }

        $url = $response->json('url');

        return is_string($url) ? $url : null;
    }

    /** Ensure a Stripe Customer exists for this customer; returns its id (stored on the model). */
    public function ensureStripeCustomer(Customer $customer): ?string
    {
        $existing = $customer->getAttribute('stripe_customer_id');
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            return null;
        }

        $response = Http::asForm()->withToken($secret)->post('https://api.stripe.com/v1/customers', [
            'email' => $customer->email,
            'name' => $customer->displayName(),
            'metadata[customer_id]' => (string) $customer->id,
            'metadata[tenant_id]' => (string) $customer->getAttribute('tenant_id'),
        ]);

        $id = $response->successful() ? $response->json('id') : null;
        if (! is_string($id)) {
            return null;
        }

        $customer->forceFill(['stripe_customer_id' => $id])->save();

        return $id;
    }

    /** Hosted Checkout in setup mode to save a card for autopay (no charge). */
    public function createSetupCheckout(Customer $customer, string $successUrl, string $cancelUrl): ?string
    {
        $secret = (string) config('services.stripe.secret');
        $stripeCustomer = $this->ensureStripeCustomer($customer);
        if ($secret === '' || $stripeCustomer === null) {
            return null;
        }

        $response = Http::asForm()->withToken($secret)->post('https://api.stripe.com/v1/checkout/sessions', [
            'mode' => 'setup',
            'customer' => $stripeCustomer,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata[customer_id]' => (string) $customer->id,
            'metadata[tenant_id]' => (string) $customer->getAttribute('tenant_id'),
        ]);

        $url = $response->successful() ? $response->json('url') : null;

        return is_string($url) ? $url : null;
    }

    /**
     * Read the saved card off a completed setup Checkout session.
     *
     * @return array{payment_method: string, brand: ?string, last4: ?string, exp_month: ?int, exp_year: ?int}|null
     */
    public function retrieveSetupCard(string $sessionId): ?array
    {
        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            return null;
        }

        $response = Http::withToken($secret)->get('https://api.stripe.com/v1/checkout/sessions/'.$sessionId, [
            'expand' => ['setup_intent.payment_method'],
        ]);
        if (! $response->successful()) {
            return null;
        }

        $pm = $response->json('setup_intent.payment_method');
        if (! is_array($pm) || ! is_string($pm['id'] ?? null)) {
            return null;
        }
        $card = is_array($pm['card'] ?? null) ? $pm['card'] : [];

        return [
            'payment_method' => $pm['id'],
            'brand' => is_string($card['brand'] ?? null) ? $card['brand'] : null,
            'last4' => is_string($card['last4'] ?? null) ? $card['last4'] : null,
            'exp_month' => is_int($card['exp_month'] ?? null) ? $card['exp_month'] : null,
            'exp_year' => is_int($card['exp_year'] ?? null) ? $card['exp_year'] : null,
        ];
    }

    /**
     * Charge a saved card off-session (autopay). A decline comes back as a 402
     * with the PaymentIntent nested under `error.payment_intent`.
     *
     * @return array{status: string, id: ?string}|null
     */
    public function chargeOffSession(string $stripeCustomerId, string $paymentMethodId, float $amount, int $customerId, int $tenantId): ?array
    {
        $secret = (string) config('services.stripe.secret');
        if ($secret === '' || $amount <= 0) {
            return null;
        }

        $response = Http::asForm()->withToken($secret)->post('https://api.stripe.com/v1/payment_intents', [
            'amount' => (int) round($amount * 100),
            'currency' => 'usd',
            'customer' => $stripeCustomerId,
            'payment_method' => $paymentMethodId,
            'off_session' => 'true',
            'confirm' => 'true',
            'metadata[customer_id]' => (string) $customerId,
            'metadata[tenant_id]' => (string) $tenantId,
        ]);

        $status = $response->json('status') ?? $response->json('error.payment_intent.status');
        $id = $response->json('id') ?? $response->json('error.payment_intent.id');

        return [
            'status' => is_string($status) ? $status : 'failed',
            'id' => is_string($id) ? $id : null,
        ];
    }
}
