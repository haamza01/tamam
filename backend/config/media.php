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
];
