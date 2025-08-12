<?php

return [
    /*
    |--------------------------------------------------------------------------
    | File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for file uploads including
    | maximum file sizes, allowed file types, and storage settings.
    |
    */

    'max_file_size' => env('MAX_FILE_SIZE', 25 * 1024 * 1024), // 25MB in bytes

    'allowed_image_types' => [
        'image/jpeg',
        'image/jpg',
        'image/png', 
        'image/gif',
        'image/webp'
    ],

    'storage_disk' => env('FILESYSTEM_DISK', 'public'),

    'directories' => [
        'profile_photos' => 'profile-photos',
        'client_photos' => 'clients/photos',
        'location_photos' => 'locations/photos',
        'report_photos' => 'reports/photos',
        'site_assets' => 'site-assets',
    ],

    'image_quality' => env('IMAGE_QUALITY', 85),

    'generate_thumbnails' => env('GENERATE_THUMBNAILS', true),

    'thumbnail_sizes' => [
        'small' => [150, 150],
        'medium' => [300, 300],
        'large' => [600, 600],
    ],
];
