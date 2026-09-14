<?php

declare(strict_types=1);

return [
    'enabled' => env('CAPELL_REFERER_ENABLED', true),
    'retention_days' => 400,
    'cache_ttl_seconds' => 60,
    'write_timeout_ms' => 1000,
    'max_source_mappings' => 500,
    'max_header_length' => 2048,
    'health_signal_ttl_seconds' => 300,
    'source_mappings' => [
        'google' => [
            'google.com',
            'google.co.uk',
            'google.ca',
            'google.com.au',
            'google.de',
            'google.fr',
        ],
        'bing' => ['bing.com'],
        'duckduckgo' => ['duckduckgo.com'],
        'yahoo' => ['yahoo.com', 'search.yahoo.com'],
        'facebook' => ['facebook.com', 'fb.com'],
        'instagram' => ['instagram.com'],
        'linkedin' => ['linkedin.com'],
        'youtube' => ['youtube.com', 'youtu.be'],
    ],
];
