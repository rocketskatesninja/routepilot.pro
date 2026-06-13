<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;

/**
 * Thin Stripe gateway over the HTTP API (no SDK dependency; Http::fake()-able).
 * Reads the secret key from config('services.stripe.*'). Returns null when
 * Stripe isn't configured so callers can degrade gracefully.
 *
 * Customer payments are routed to the tenant's connected account as destination
 * charges (with a platform application fee) once that account can accept charges;
 * otherwise they land on the platform account.
 */
class StripeService
{
    public function configured(): bool
    {
        return (string) config('services.stripe.secret') !== '';
    }

    /** The tenant's connected account if it can accept charges, else null (→ platform). */
    public function connectAccountFor(Customer $customer): ?string
    {
        $tenant = $customer->tenant;
        $account = $tenant->getAttribute('stripe_connect_account_id');

        return is_string($account) && $account !== '' && (bool) $tenant->getAttribute('stripe_connect_charges_enabled')
            ? $account
            : null;
    }

    private function applicationFee(float $amount): int
    {
        return (int) round($amount * (float) config('services.stripe.application_fee_percent'));
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

        $params = [
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
        ];

        $connect = $this->connectAccountFor($customer);
        if ($connect !== null) {
            $params['payment_intent_data[transfer_data][destination]'] = $connect;
            $params['payment_intent_data[application_fee_amount]'] = $this->applicationFee($amount);
        }

        $response = Http::asForm()->withToken($secret)->post('https://api.stripe.com/v1/checkout/sessions', $params);
        $url = $response->successful() ? $response->json('url') : null;

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
     * @return array{payment_method: string, customer_id: ?int, brand: ?string, last4: ?string, exp_month: ?int, exp_year: ?int}|null
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
        // The customer id we stamped into the session metadata — the caller
        // asserts it matches the acting customer (session_id is user-supplied).
        $metaCustomerId = $response->json('metadata.customer_id');

        return [
            'payment_method' => $pm['id'],
            'customer_id' => is_numeric($metaCustomerId) ? (int) $metaCustomerId : null,
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
    public function chargeOffSession(string $stripeCustomerId, string $paymentMethodId, float $amount, int $customerId, int $tenantId, ?string $connectAccount = null): ?array
    {
        $secret = (string) config('services.stripe.secret');
        if ($secret === '' || $amount <= 0) {
            return null;
        }

        $params = [
            'amount' => (int) round($amount * 100),
            'currency' => 'usd',
            'customer' => $stripeCustomerId,
            'payment_method' => $paymentMethodId,
            'off_session' => 'true',
            'confirm' => 'true',
            'metadata[customer_id]' => (string) $customerId,
            'metadata[tenant_id]' => (string) $tenantId,
        ];
        if ($connectAccount !== null && $connectAccount !== '') {
            $params['transfer_data[destination]'] = $connectAccount;
            $params['application_fee_amount'] = $this->applicationFee($amount);
        }

        $response = Http::asForm()->withToken($secret)->post('https://api.stripe.com/v1/payment_intents', $params);

        $status = $response->json('status') ?? $response->json('error.payment_intent.status');
        $id = $response->json('id') ?? $response->json('error.payment_intent.id');

        return [
            'status' => is_string($status) ? $status : 'failed',
            'id' => is_string($id) ? $id : null,
        ];
    }

    /** Create an Express connected account for the tenant; returns its id (stored). */
    public function createConnectAccount(Tenant $tenant): ?string
    {
        $existing = $tenant->getAttribute('stripe_connect_account_id');
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            return null;
        }

        $response = Http::asForm()->withToken($secret)->post('https://api.stripe.com/v1/accounts', [
            'type' => 'express',
            'metadata[tenant_id]' => (string) $tenant->id,
            'capabilities[card_payments][requested]' => 'true',
            'capabilities[transfers][requested]' => 'true',
        ]);

        $id = $response->successful() ? $response->json('id') : null;
        if (! is_string($id)) {
            return null;
        }

        $tenant->forceFill(['stripe_connect_account_id' => $id])->save();

        return $id;
    }

    /** Hosted onboarding link for the tenant to finish connecting their account. */
    public function createAccountLink(string $accountId, string $refreshUrl, string $returnUrl): ?string
    {
        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            return null;
        }

        $response = Http::asForm()->withToken($secret)->post('https://api.stripe.com/v1/account_links', [
            'account' => $accountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);

        $url = $response->successful() ? $response->json('url') : null;

        return is_string($url) ? $url : null;
    }

    /** Re-check the connected account and store whether it can accept charges. */
    public function refreshConnectStatus(Tenant $tenant): bool
    {
        $account = $tenant->getAttribute('stripe_connect_account_id');
        $secret = (string) config('services.stripe.secret');
        if (! is_string($account) || $account === '' || $secret === '') {
            return false;
        }

        $response = Http::withToken($secret)->get('https://api.stripe.com/v1/accounts/'.$account);
        if (! $response->successful()) {
            return (bool) $tenant->getAttribute('stripe_connect_charges_enabled');
        }

        $enabled = (bool) $response->json('charges_enabled');
        $tenant->forceFill(['stripe_connect_charges_enabled' => $enabled])->save();

        return $enabled;
    }
}
