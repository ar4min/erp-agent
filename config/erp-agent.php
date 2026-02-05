<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Control Plane Configuration
    |--------------------------------------------------------------------------
    */
    'control_plane' => [
        'url' => env('CONTROL_PLANE_URL', 'http://localhost:8001'),
        'token' => env('CONTROL_PLANE_TOKEN', ''),
        'timeout' => env('CONTROL_PLANE_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Instance Configuration
    |--------------------------------------------------------------------------
    */
    'instance' => [
        'id' => env('INSTANCE_ID', ''),
        'machine_id' => env('MACHINE_ID', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | License Configuration
    |--------------------------------------------------------------------------
    */
    'license' => [
        'key' => env('LICENSE_KEY', ''),
        'validation_interval' => env('LICENSE_VALIDATION_INTERVAL', 6 * 60 * 60), // 6 hours
        'cache_ttl' => env('LICENSE_CACHE_TTL', 24 * 60 * 60), // 24 hours
        'grace_period' => env('LICENSE_GRACE_PERIOD', 72 * 60 * 60), // 72 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Heartbeat Configuration
    |--------------------------------------------------------------------------
    */
    'heartbeat' => [
        'enabled' => env('HEARTBEAT_ENABLED', true),
        'interval' => env('HEARTBEAT_INTERVAL', 60), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        // Redirect to this route when license is invalid
        'redirect_to' => env('LICENSE_INVALID_REDIRECT', '/license-expired'),

        // Routes that don't require license check
        'except' => [
            'login',
            'logout',
            'license-expired',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Tracing (Telescope-like)
    |--------------------------------------------------------------------------
    | Collects per-request data (queries, models, cache, performance) and
    | sends to Control Plane for centralized monitoring via Kibana.
    */
    'tracing' => [
        'enabled' => env('ERP_TRACING_ENABLED', true),
        'batch_size' => env('ERP_TRACING_BATCH_SIZE', 50),
        'max_queue_size' => env('ERP_TRACING_MAX_QUEUE', 2000),
        'flush_interval' => env('ERP_TRACING_FLUSH_INTERVAL', 60), // seconds

        // Paths to exclude from tracing
        'exclude_paths' => [
            '_debugbar/*',
            'telescope/*',
            'horizon/*',
            'livewire/*',
            'sanctum/*',
            'license-expired',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Forwarding Configuration
    |--------------------------------------------------------------------------
    | Forward application logs to the Control Plane for centralized monitoring.
    | Logs are queued locally and sent in batches.
    |
    | To use as a Laravel log channel, add to config/logging.php channels:
    |   'control_plane' => [
    |       'driver' => 'custom',
    |       'via' => \Ar4min\ErpAgent\Logging\ControlPlaneLogger::class,
    |       'level' => 'info',
    |   ],
    */
    'logging' => [
        'forwarding_enabled' => env('ERP_LOG_FORWARDING', true),
        'forward_interval' => env('ERP_LOG_FORWARD_INTERVAL', 300), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Support Contact Information
    |--------------------------------------------------------------------------
    */
    'support' => [
        'phone' => env('SUPPORT_PHONE', '021-12345678'),
        'email' => env('SUPPORT_EMAIL', 'support@example.com'),
        'url' => env('SUPPORT_URL', '/support'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Microsoft Clarity Analytics
    |--------------------------------------------------------------------------
    | Clarity provides heatmaps, session recordings, and user behavior analytics.
    | Get your project ID from: https://clarity.microsoft.com
    */
    'clarity' => [
        'enabled' => env('CLARITY_ENABLED', true),
        'project_id' => env('CLARITY_PROJECT_ID', ''),

        // Auto-inject script into all HTML responses
        'auto_inject' => env('CLARITY_AUTO_INJECT', true),

        // Track tenant/instance info for filtering in Clarity dashboard
        'track_tenant' => env('CLARITY_TRACK_TENANT', true),

        // Exclude these routes from tracking
        'exclude_routes' => [
            'login',
            'logout',
            'password/*',
            'license-expired',
        ],

        // Exclude these IPs (e.g., developers, admins)
        'exclude_ips' => [
            // '127.0.0.1',
        ],
    ],
];
