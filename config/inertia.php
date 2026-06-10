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
];
