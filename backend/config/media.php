<?php

return [
    'avatar' => [
        'disk' => env('AVATAR_DISK', 'public_assets'),
        'max_kb' => (int) env('AVATAR_MAX_KB', 5120),
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'path_prefix' => 'avatars',
        'default_url' => env('AVATAR_DEFAULT_URL', '/images/default-avatar.svg'),
    ],

    'listing' => [
        'disk' => env('LISTING_IMAGE_DISK', 'public_assets'),
        'max_kb' => (int) env('LISTING_IMAGE_MAX_KB', 10240),
        'max_count' => (int) env('LISTING_IMAGE_MAX_COUNT', 20),
        'min_ready_for_submit' => 1,
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'path_prefix' => 'listings',
        'source_disk' => env('LISTING_IMAGE_SOURCE_DISK', 'local'),
        'max_width' => (int) env('LISTING_IMAGE_MAX_WIDTH', 1920),
        'thumbnail_width' => (int) env('LISTING_IMAGE_THUMBNAIL_WIDTH', 400),
        'webp_quality' => (int) env('LISTING_IMAGE_WEBP_QUALITY', 82),
        'max_pixels' => (int) env('LISTING_IMAGE_MAX_PIXELS', 40_000_000),
        'max_dimension' => (int) env('LISTING_IMAGE_MAX_DIMENSION', 8000),
    ],
];
