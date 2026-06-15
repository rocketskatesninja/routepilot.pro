<?php

declare(strict_types=1);

/*
 * Platform billing — RoutePilot's "base + per-pool" plan. A flat base includes
 * a pool + agent allowance; usage beyond it is metered as overage. Stripe price
 * IDs for the live subscription are added in a later billing slice.
 */
return [
    'base_price' => (float) env('BILLING_BASE_PRICE', 34.99),
    'included_pools' => (int) env('BILLING_INCLUDED_POOLS', 50),
    'included_agents' => (int) env('BILLING_INCLUDED_AGENTS', 2),
    'price_per_pool' => (float) env('BILLING_PRICE_PER_POOL', 0.50),
    'price_per_agent' => (float) env('BILLING_PRICE_PER_AGENT', 10.00),

    // Stripe Price IDs for the live subscription (test-mode or live). The billing
    // screen falls back to a "not configured yet" state until the base price is set.
    'prices' => [
        'base' => env('STRIPE_PRICE_BASE'),
        'pool' => env('STRIPE_PRICE_POOL'),
        'agent' => env('STRIPE_PRICE_AGENT'),
    ],
];
