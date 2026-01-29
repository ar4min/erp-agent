<?php

namespace Ar4min\ErpAgent\Logging;

use Ar4min\ErpAgent\Services\LogForwarder;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Monolog\Level;

class ControlPlaneHandler extends AbstractProcessingHandler
{
    protected LogForwarder $forwarder;

    public function __construct(LogForwarder $forwarder, int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->forwarder = $forwarder;
    }

    protected function write(LogRecord $record): void
    {
        $context = $record->context;
        $category = $context['_category'] ?? LogForwarder::detectCategory($record->message, $context);

        // Remove internal category hint from context
        unset($context['_category']);

        $level = $record->level->name;

        $this->forwarder->queue($level, $category, $record->message, $context);
    }
}
