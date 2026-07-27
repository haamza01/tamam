<?php

return [
    'refresh_cookie' => env('AUTH_REFRESH_COOKIE', 'tamam_refresh_token'),
    'csrf_cookie' => env('AUTH_CSRF_COOKIE', 'tamam_auth_csrf'),
    'refresh_ttl_days' => (int) env('JWT_REFRESH_TTL_DAYS', 14),
    'secure' => env('AUTH_COOKIE_SECURE', env('APP_ENV') === 'production'),
    'same_site' => env('AUTH_COOKIE_SAME_SITE', 'lax'),
    'path' => env('AUTH_COOKIE_PATH', '/api/v1/auth'),
];
