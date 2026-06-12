<?php

declare(strict_types=1);

return [
    /*
     * Inertia testing: assert pages exist on disk. This project uses the
     * lowercase `resources/js/pages` convention (Laravel Vue starter kit),
     * not Inertia's default capital-`Pages`.
     */
    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [resource_path('js/pages')],
        'page_extensions' => ['js', 'jsx', 'ts', 'tsx', 'vue'],
    ],

    /*
     * Server-side rendering. The public landing pages (and the whole app) are
     * pre-rendered by a Node process (`php artisan inertia:start-ssr`) reading
     * the bundle below. Env-gated so it can be killed instantly without a
     * deploy — Inertia falls back to client rendering when disabled/unreachable.
     */
    'ssr' => [
        'enabled' => env('INERTIA_SSR_ENABLED', true),
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
        'bundle' => base_path('bootstrap/ssr/ssr.js'),
    ],
];
