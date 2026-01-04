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
    | Support Contact Information
    |--------------------------------------------------------------------------
    */
    'support' => [
        'phone' => env('SUPPORT_PHONE', '021-12345678'),
        'email' => env('SUPPORT_EMAIL', 'support@example.com'),
        'url' => env('SUPPORT_URL', '/support'),
    ],
];
