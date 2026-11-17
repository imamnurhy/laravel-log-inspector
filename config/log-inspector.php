<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Log Inspector Master Switch
    |--------------------------------------------------------------------------
    |
    | Enables or disables the log inspector dashboard and API endpoints.
    | Set to false in production unless explicitly authorized.
    |
    */
    'enabled' => env('LOG_INSPECTOR_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Allowed Environments
    |--------------------------------------------------------------------------
    |
    | The dashboard will only be accessible in these environments.
    |
    */
    'enabled_environments' => ['local', 'staging'],

    /*
    |--------------------------------------------------------------------------
    | Dashboard URL Path & Domain
    |--------------------------------------------------------------------------
    */
    'path' => 'log-inspector',
    'domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Middleware Pipeline
    |--------------------------------------------------------------------------
    |
    | Middleware applied to all Log Inspector routes. By default, uses web and auth.
    |
    */
    'middleware' => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Authorization Gates
    |--------------------------------------------------------------------------
    |
    | Log Inspector relies on Laravel Gate definitions in your application.
    | If a gate is not defined by the host app, access is denied by default.
    |
    */
    'authorization' => [
        'view' => 'view-log-inspector',
        'download' => 'download-log-inspector',
        'download_raw' => 'download-raw-log-inspector',
        'delete' => 'delete-log-inspector',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Log Storage Directories (Whitelist)
    |--------------------------------------------------------------------------
    |
    | Only files strictly inside these directories can be read, downloaded, or deleted.
    | Symlink breakouts and directory traversals outside these paths are rejected.
    |
    */
    'paths' => [
        storage_path('logs'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Active Log Names & Protection
    |--------------------------------------------------------------------------
    |
    | Active log files cannot be deleted. Daily log for current date is automatically
    | considered active along with any file modified within threshold seconds.
    |
    */
    'active_logs' => [
        'names' => ['laravel.log'],
        'mtime_threshold' => 300, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing & Scan Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'per_page' => 50,
        'max_per_page' => 200,
        'max_scan_mb' => 50,
        'max_scan_seconds' => 5,
        'max_search_length' => 200,
        'chunk_size' => 65536, // 64 KB
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Requests per minute limits for sensitive endpoints.
    |
    */
    'rate_limit' => [
        'search' => '30,1',
        'download' => '10,1',
        'delete' => '5,1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sidecar Index & Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'store' => null, // null defaults to local index directory storage
        'ttl' => 300,
        'index_path' => storage_path('framework/cache/log-inspector/indices'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Data Masking
    |--------------------------------------------------------------------------
    */
    'masking' => [
        'enabled' => true,
        'patterns' => [],
        'extra_patterns' => [],
        'replacement' => '******',
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Channel used to record download, delete, and access operations.
    | Must NOT be the primary channel being inspected to prevent recursive loops.
    |
    */
    'audit' => [
        'enabled' => true,
        'channel' => null, // null falls back to default logger with explicit tag
    ],
];
