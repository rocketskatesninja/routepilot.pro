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
}
