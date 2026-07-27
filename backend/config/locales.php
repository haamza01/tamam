<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supported Application Locales
    |--------------------------------------------------------------------------
    |
    | Mirrors shared/src/constants/locales.ts and ADR-003. Used for API
    | locale resolution and catalog cache invalidation.
    |
    */
    'supported' => ['ar', 'en'],

    'default' => 'ar',

    'fallback' => 'ar',
];
