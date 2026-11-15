<?php

declare(strict_types=1);

/*
 * Inertia v3. Every key of the package's own config is written out here: Laravel
 * merges a package's config one level deep only, so a section left half-filled
 * (e.g. `ssr` with just three keys) would drop the package's other defaults.
 */
return [
    /*
     * Server-side rendering. The public landing pages (and the whole app) are
     * pre-rendered by a Node process (`php artisan inertia:start-ssr`) reading
     * the bundle below. Env-gated so it can be killed instantly without a
     * deploy — Inertia falls back to client rendering when disabled/unreachable.
     */
    'ssr' => [
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', true),
        'runtime' => env('INERTIA_SSR_RUNTIME', 'node'),
        'ensure_runtime_exists' => (bool) env('INERTIA_SSR_ENSURE_RUNTIME_EXISTS', false),
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
        'hot_url' => env('INERTIA_SSR_HOT_URL'),
        'timeout' => env('INERTIA_SSR_TIMEOUT'),
        'ensure_bundle_exists' => (bool) env('INERTIA_SSR_ENSURE_BUNDLE_EXISTS', true),
        'bundle' => base_path('bootstrap/ssr/ssr.js'),
        'throw_on_error' => (bool) env('INERTIA_SSR_THROW_ON_ERROR', false),
    ],

    /*
     * Where the page components live. This project uses the lowercase
     * `resources/js/pages` convention (Laravel Vue starter kit), not Inertia's
     * capital-`Pages`. Tests assert each rendered page exists on disk.
     */
    'pages' => [
        'ensure_pages_exist' => false,
        'paths' => [resource_path('js/pages')],
        'extensions' => ['js', 'jsx', 'ts', 'tsx', 'vue'],
    ],

    'testing' => [
        'ensure_pages_exist' => true,
    ],

    'expose_shared_prop_keys' => true,

    'store_previous_url' => false,

    'history' => [
        'encrypt' => (bool) env('INERTIA_ENCRYPT_HISTORY', false),
    ],

    /*
     * Inertia's request recorder (routes under /_inertia/devtools). Off unless
     * INERTIA_DEVTOOLS_ENABLED=true: the package would otherwise switch it on for
     * any APP_ENV=local box on its own.
     */
    'devtools' => [
        'enabled' => (bool) env('INERTIA_DEVTOOLS_ENABLED', false),
        'except' => ['telescope*', 'horizon*', '_inertia/devtools*'],
        'storage' => [
            'path' => storage_path('inertia-devtools'),
            'ttl' => (int) env('INERTIA_DEVTOOLS_TTL_HOURS', 24),
            'prune_interval' => (int) env('INERTIA_DEVTOOLS_PRUNE_INTERVAL_SECONDS', 300),
            'limit' => (int) env('INERTIA_DEVTOOLS_LIMIT', 100),
        ],
        'middleware' => ['web'],
        'gate' => env('INERTIA_DEVTOOLS_GATE'),
        'redact' => [
            'keys' => ['password', 'password_confirmation', 'current_password', 'token', '_token', 'access_token', 'refresh_token', 'secret', 'client_secret', 'api_key'],
            'headers' => ['cookie', 'set-cookie', 'authorization', 'proxy-authorization', 'x-xsrf-token', 'x-csrf-token'],
        ],
    ],

    'preserve_big_integers' => (bool) env('INERTIA_PRESERVE_BIG_INTEGERS', false),
];
