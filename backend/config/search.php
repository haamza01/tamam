<?php

return [
    'fts_config' => env('SEARCH_FTS_CONFIG', 'simple'),

    'keyword' => [
        'min_length' => (int) env('SEARCH_KEYWORD_MIN_LENGTH', 2),
        'max_length' => (int) env('SEARCH_KEYWORD_MAX_LENGTH', 200),
        'max_tokens' => (int) env('SEARCH_KEYWORD_MAX_TOKENS', 10),
    ],

    'pagination' => [
        'default' => (int) env('SEARCH_PER_PAGE_DEFAULT', 20),
        'max' => (int) env('SEARCH_PER_PAGE_MAX', 100),
    ],

    'suggestions' => [
        'min_prefix_length' => (int) env('SEARCH_SUGGESTION_MIN_PREFIX', 2),
        'max_results' => (int) env('SEARCH_SUGGESTION_MAX_RESULTS', 10),
    ],

    'max_attribute_filters' => (int) env('SEARCH_MAX_ATTRIBUTE_FILTERS', 20),

    'popular' => [
        'max_results' => (int) env('SEARCH_POPULAR_MAX_RESULTS', 10),
        'cache_ttl' => (int) env('SEARCH_POPULAR_CACHE_TTL', 3600),
    ],

    'rate_limits' => [
        'search_per_minute' => (int) env('SEARCH_RATE_LIMIT', 60),
        'suggestions_per_minute' => (int) env('SEARCH_SUGGESTIONS_RATE_LIMIT', 120),
        'popular_per_minute' => (int) env('SEARCH_POPULAR_RATE_LIMIT', 60),
    ],

    'cache_keys' => [
        'popular' => 'search:popular',
        'category_descendants' => 'search:category-descendants',
    ],
];
