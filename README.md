# ERP Agent

Laravel package for connecting ERP instances to Control Plane. Handles heartbeat, license validation, and more.

## Installation

```bash
composer require ar4min/erp-agent
```

## Quick Setup

```bash
php artisan erp:install
```

This will:
1. Publish the configuration file
2. Ask for Control Plane credentials
3. Update your `.env` file
4. Test the connection

## Manual Configuration

### 1. Publish Config

```bash
php artisan vendor:publish --tag=erp-agent-config
```

### 2. Add to `.env`

```env
CONTROL_PLANE_URL=http://your-control-plane.com
CONTROL_PLANE_TOKEN=your-service-token
INSTANCE_ID=5
LICENSE_KEY=XXXX-XXXX-XXXX-XXXX-XXXX
MACHINE_ID=unique-machine-id
```

## Commands

### Test Connection
```bash
php artisan erp:test-connection
```

### Heartbeat
```bash
# Send once
php artisan erp:heartbeat --once

# Show metrics without sending
php artisan erp:heartbeat --show

# Continuous (every 60s)
php artisan erp:heartbeat
```

### License
```bash
# Validate
php artisan erp:license

# Force refresh
php artisan erp:license --refresh

# Show status
php artisan erp:license --status
```

## Scheduler Setup

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Heartbeat every minute
    $schedule->command('erp:heartbeat --once')->everyMinute();

    // License validation every 6 hours
    $schedule->command('erp:license --refresh')->everySixHours();
}
```

## Middleware

### License Verification

Protect routes that require valid license:

```php
// In routes/web.php
Route::middleware(['erp.license'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    // ... other protected routes
});
```

### Response Time Tracking

Track response times for heartbeat metrics:

```php
// In app/Http/Kernel.php
protected $middleware = [
    // ...
    \Ar4min\ErpAgent\Middleware\TrackResponseTime::class,
];
```

## Using Services

### In Controllers

```php
use Ar4min\ErpAgent\Services\LicenseService;
use Ar4min\ErpAgent\Services\HeartbeatService;

class DashboardController extends Controller
{
    public function index(LicenseService $license)
    {
        // Check if module is enabled
        if (!$license->hasModule('accounting')) {
            abort(403, 'Module not available');
        }

        // Get license info
        $status = $license->getStatus();

        return view('dashboard', compact('status'));
    }
}
```

### Check Module Access

```php
$license = app(LicenseService::class);

if ($license->hasModule('hr')) {
    // HR module features
}

if ($license->hasModule('crm')) {
    // CRM module features
}
```

### Get License Status

```php
$status = $license->getStatus();

// $status contains:
// - valid: bool
// - modules: array
// - expires_at: string
// - days_until_expiration: int
// - tenant_name: string
// - plan_name: string
// - in_grace_period: bool
// - grace_remaining: int (seconds)
```

## Configuration

See `config/erp-agent.php` for all options:

```php
return [
    'control_plane' => [
        'url' => env('CONTROL_PLANE_URL'),
        'token' => env('CONTROL_PLANE_TOKEN'),
        'timeout' => 30,
    ],

    'instance' => [
        'id' => env('INSTANCE_ID'),
        'machine_id' => env('MACHINE_ID'),
    ],

    'license' => [
        'key' => env('LICENSE_KEY'),
        'validation_interval' => 6 * 60 * 60, // 6 hours
        'cache_ttl' => 24 * 60 * 60, // 24 hours
        'grace_period' => 72 * 60 * 60, // 72 hours offline
    ],

    'heartbeat' => [
        'enabled' => true,
        'interval' => 60,
    ],

    'middleware' => [
        'redirect_to' => '/license-expired',
        'except' => ['login', 'logout', 'license-expired'],
    ],
];
```

## Grace Period

When Control Plane is unreachable:
1. System continues working using cached license
2. Grace period starts (default: 72 hours)
3. After grace period expires, license becomes invalid

## Microsoft Clarity Analytics

Built-in support for Microsoft Clarity user behavior analytics (heatmaps, session recordings).

### Setup

1. Get your Project ID from [clarity.microsoft.com](https://clarity.microsoft.com)
2. Add to `.env`:

```env
CLARITY_ENABLED=true
CLARITY_PROJECT_ID=your-project-id
```

That's it! The script is automatically injected into all HTML responses.

### Features

- **Auto-injection**: No code changes needed in your views
- **Tenant tracking**: Automatically tracks `instance_id` and `tenant_name` for filtering
- **User tracking**: Tracks logged-in user ID (hashed email for privacy)
- **Route exclusion**: Login/logout pages are excluded by default
- **IP exclusion**: Exclude developer IPs from tracking

### Configuration

```php
// config/erp-agent.php
'clarity' => [
    'enabled' => env('CLARITY_ENABLED', true),
    'project_id' => env('CLARITY_PROJECT_ID', ''),
    'auto_inject' => true,  // Inject script automatically
    'track_tenant' => true, // Track tenant/instance info
    'exclude_routes' => ['login', 'logout', 'password/*'],
    'exclude_ips' => ['127.0.0.1'],  // Developer IPs
],
```

### Filtering in Clarity Dashboard

In Clarity, you can filter sessions by:
- `instance_id` - Specific ERP instance
- `tenant_name` - Tenant/company name
- `user_id` - Specific user
- `user_hash` - Hashed user email

## License

MIT
