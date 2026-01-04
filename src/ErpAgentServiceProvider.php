<?php

namespace Ar4min\ErpAgent;

use Illuminate\Support\ServiceProvider;
use Ar4min\ErpAgent\Services\ControlPlaneClient;
use Ar4min\ErpAgent\Services\HeartbeatService;
use Ar4min\ErpAgent\Services\LicenseService;
use Ar4min\ErpAgent\Commands\InstallCommand;
use Ar4min\ErpAgent\Commands\HeartbeatCommand;
use Ar4min\ErpAgent\Commands\LicenseCommand;
use Ar4min\ErpAgent\Commands\TestConnectionCommand;

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

        // Register facade aliases
        $this->app->alias(LicenseService::class, 'erp-license');
        $this->app->alias(HeartbeatService::class, 'erp-heartbeat');
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
            ]);
        }

        // Register middleware alias
        $router = $this->app->make('router');
        $router->aliasMiddleware('erp.license', \Ar4min\ErpAgent\Middleware\VerifyLicense::class);
    }
}
