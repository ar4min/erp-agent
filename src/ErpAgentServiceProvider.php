<?php

namespace Ar4min\ErpAgent;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Ar4min\ErpAgent\Services\ControlPlaneClient;
use Ar4min\ErpAgent\Services\HeartbeatService;
use Ar4min\ErpAgent\Services\LicenseService;
use Ar4min\ErpAgent\Services\LogForwarder;
use Ar4min\ErpAgent\Services\RequestTracerService;
use Ar4min\ErpAgent\Commands\InstallCommand;
use Ar4min\ErpAgent\Commands\HeartbeatCommand;
use Ar4min\ErpAgent\Commands\LicenseCommand;
use Ar4min\ErpAgent\Commands\TestConnectionCommand;
use Ar4min\ErpAgent\Commands\ForwardLogsCommand;
use Ar4min\ErpAgent\Commands\FlushTracesCommand;
use Ar4min\ErpAgent\Middleware\InjectClarity;

class ErpAgentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__ . '/../config/erp-agent.php', 'erp-agent');

        // Register services as singletons
        $this->app->singleton(ControlPlaneClient::class, function ($app) {
            return new ControlPlaneClient();
        });

        $this->app->singleton(HeartbeatService::class, function ($app) {
            return new HeartbeatService($app->make(ControlPlaneClient::class));
        });

        $this->app->singleton(LicenseService::class, function ($app) {
            return new LicenseService($app->make(ControlPlaneClient::class));
        });

        $this->app->singleton(LogForwarder::class, function ($app) {
            return new LogForwarder($app->make(ControlPlaneClient::class));
        });

        $this->app->singleton(RequestTracerService::class, function ($app) {
            return new RequestTracerService($app->make(ControlPlaneClient::class));
        });

        // Register facade aliases
        $this->app->alias(LicenseService::class, 'erp-license');
        $this->app->alias(HeartbeatService::class, 'erp-heartbeat');
        $this->app->alias(LogForwarder::class, 'erp-log-forwarder');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/erp-agent.php' => config_path('erp-agent.php'),
        ], 'erp-agent-config');

        // Publish views
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/erp-agent'),
        ], 'erp-agent-views');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'erp-agent');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                HeartbeatCommand::class,
                LicenseCommand::class,
                TestConnectionCommand::class,
                ForwardLogsCommand::class,
                FlushTracesCommand::class,
            ]);
        }

        // Register middleware alias
        $router = $this->app->make('router');
        $router->aliasMiddleware('erp.license', \Ar4min\ErpAgent\Middleware\VerifyLicense::class);
        $router->aliasMiddleware('erp.clarity', InjectClarity::class);
        $router->aliasMiddleware('erp.tracer', \Ar4min\ErpAgent\Middleware\RequestTracer::class);

        $kernel = $this->app->make(Kernel::class);

        // Auto-register License middleware globally (ENFORCED - cannot be disabled)
        // This ensures license validation on ALL routes except login/logout
        $kernel->pushMiddleware(\Ar4min\ErpAgent\Middleware\VerifyLicense::class);

        // Auto-register Request Tracer middleware globally if enabled
        if (config('erp-agent.tracing.enabled', true)) {
            $kernel->pushMiddleware(\Ar4min\ErpAgent\Middleware\RequestTracer::class);
        }

        // Auto-register Clarity middleware globally if enabled
        if (config('erp-agent.clarity.enabled') && config('erp-agent.clarity.auto_inject')) {
            $kernel->pushMiddleware(InjectClarity::class);
        }
    }
}
