<?php

return [
    'cache_ttl' => (int) env('CATALOG_CACHE_TTL', 3600),

    'cache_keys' => [
        'categories_flat' => 'catalog:categories:flat:active',
        'categories_tree' => 'catalog:categories:tree:active',
        'locations_flat' => 'catalog:locations:flat:active',
        'locations_tree' => 'catalog:locations:tree:active',
    ],
];
