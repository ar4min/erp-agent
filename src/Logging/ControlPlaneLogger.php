<?php

namespace Ar4min\ErpAgent\Logging;

use Ar4min\ErpAgent\Services\LogForwarder;
use Monolog\Logger;

class ControlPlaneLogger
{
    /**
     * Create a custom Monolog instance for Laravel logging config.
     *
     * Usage in config/logging.php:
     *   'control_plane' => [
     *       'driver' => 'custom',
     *       'via' => \Ar4min\ErpAgent\Logging\ControlPlaneLogger::class,
     *       'level' => 'info',
     *   ],
     */
    public function __invoke(array $config): Logger
    {
        $forwarder = app(LogForwarder::class);
        $level = $config['level'] ?? 'debug';

        $logger = new Logger('control_plane');
        $logger->pushHandler(new ControlPlaneHandler($forwarder, $level));

        return $logger;
    }
}
