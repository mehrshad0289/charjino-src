<?php

return [
    'driver' => env('SMART_SEARCH_DRIVER', 'database'),
    'index_prefix' => env('SMART_SEARCH_INDEX_PREFIX', 'charjino_local_'),
    'timeout_ms' => (int) env('SMART_SEARCH_TIMEOUT_MS', 1200),
    'queue' => env('SMART_SEARCH_QUEUE', 'search'),
    'scout_sync' => filter_var(env('SMART_SEARCH_SCOUT_SYNC', false), FILTER_VALIDATE_BOOLEAN),
    'index_writes' => filter_var(env('SMART_SEARCH_INDEX_WRITES', false), FILTER_VALIDATE_BOOLEAN),
    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
        'key' => env('MEILISEARCH_KEY'),
    ],
];
