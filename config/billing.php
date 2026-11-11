<?php

declare(strict_types=1);

/*
 * Platform billing — RoutePilot's "base + per-pool" plan. A flat base includes
 * a pool allowance; pools beyond it are metered. Techs are unlimited and never
 * charged: a per-seat fee would only give a company a reason to share one login,
 * and each tech's own login is what puts their name, route and GPS on the work.
 */
return [
    'base_price' => (float) env('BILLING_BASE_PRICE', 34.99),
    'included_pools' => (int) env('BILLING_INCLUDED_POOLS', 50),
    'price_per_pool' => (float) env('BILLING_PRICE_PER_POOL', 0.75),
    // Days a new company's free trial lasts (no card needed until it ends).
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 30),

    // Stripe Price IDs for the live subscription (test-mode or live). The billing
    // screen falls back to a "not configured yet" state until the base price is set.
    'prices' => [
        'base' => env('STRIPE_PRICE_BASE'),
        'pool' => env('STRIPE_PRICE_POOL'),
    ],
];
