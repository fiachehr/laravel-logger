<?php

return [
    // No channel, request capture, or dashboard is enabled merely by installation.
    'enabled' => env('LOG_EXPLORER_ENABLED', false),
    'service' => env('LOG_EXPLORER_SERVICE', 'application'),
    'channel' => 'observability',
    'level' => env('LOG_EXPLORER_LEVEL', 'debug'),
    'storage' => [
        'root' => storage_path('logs/log-explorer'),
        'retention_days' => 14,
        'max_file_bytes' => 64 * 1024 * 1024,
        'max_files' => 14,
        'max_directory_entries' => 256,
        'max_scan_bytes' => 8 * 1024 * 1024,
        'max_scan_ms' => 250,
    ],
    'privacy' => [
        'payload_level' => 'metadata', // none | metadata | safe
        'message_mode' => 'categorical', // categorical | redacted (explicit opt-in)
        'safe_fields' => ['operation', 'count', 'code'],
        'max_depth' => 5,
        'max_items' => 30,
        'max_string_bytes' => 1000,
        'max_event_bytes' => 16384,
        'max_trace_frames' => 15,
        'max_previous_exceptions' => 2,
    ],
    'http' => [
        'trust_request_id' => false,
        'response_header' => true,
        'log_completion' => false,
    ],
    'diagnostics' => [
        'enabled' => false,
        'allow_stop' => false, // additionally restricted to local/development
    ],
    'dashboard' => [
        'enabled' => env('LOG_EXPLORER_DASHBOARD_ENABLED', false),
        'path' => env('LOG_EXPLORER_PATH', 'log-explorer'),
        'guard' => null,
        'middleware' => ['web'],
        'gate' => 'viewLogExplorer', // host must define this Gate; no permissive fallback
        'rate_per_minute' => 60,
        'max_results' => 50,
    ],
];
