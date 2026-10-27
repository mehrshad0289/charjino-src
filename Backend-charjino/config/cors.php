<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'CORS_ORIGINS',
            'http://127.0.0.1:3001,http://localhost:3001,http://127.0.0.1:3002,http://localhost:3002'
        ))
    ))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
