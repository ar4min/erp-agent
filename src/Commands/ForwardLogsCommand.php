<?php

namespace Ar4min\ErpAgent\Commands;

use Ar4min\ErpAgent\Services\LogForwarder;
use Illuminate\Console\Command;

class ForwardLogsCommand extends Command
{
    protected $signature = 'erp:forward-logs
                            {--flush : Flush all queued logs immediately}
                            {--status : Show queue status}
                            {--clear : Clear the log queue}
                            {--daemon : Run continuously, flushing on interval}';

    protected $description = 'Forward queued logs to Control Plane';

    public function handle(LogForwarder $forwarder): int
    {
        if ($this->option('status')) {
            $this->showStatus($forwarder);
            return self::SUCCESS;
        }

        if ($this->option('clear')) {
            $forwarder->clearQueue();
            $this->info('Log queue cleared.');
            return self::SUCCESS;
        }

        if ($this->option('daemon')) {
            return $this->runDaemon($forwarder);
        }

        // Default: flush once
        return $this->flushOnce($forwarder);
    }

    protected function flushOnce(LogForwarder $forwarder): int
    {
        $size = $forwarder->queueSize();

        if ($size === 0) {
            $this->info('No logs in queue.');
            return self::SUCCESS;
        }

        $this->info("Flushing {$size} queued logs...");
        $result = $forwarder->flush();

        $this->table(
            ['Sent', 'Failed', 'Remaining'],
            [[$result['sent'], $result['failed'], $result['remaining']]]
        );

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function runDaemon(LogForwarder $forwarder): int
    {
        $interval = config('erp-agent.logging.forward_interval', 300);
        $this->info("Running log forwarder daemon (interval: {$interval}s)...");
        $this->info('Press Ctrl+C to stop.');

        while (true) {
            $size = $forwarder->queueSize();

            if ($size > 0) {
                $result = $forwarder->flush();
                $this->line(sprintf(
                    '[%s] Flushed: %d sent, %d failed, %d remaining',
                    now()->format('H:i:s'),
                    $result['sent'],
                    $result['failed'],
                    $result['remaining']
                ));
            }

            sleep($interval);
        }
    }

    protected function showStatus(LogForwarder $forwarder): void
    {
        $size = $forwarder->queueSize();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Queue Size', $size],
                ['Forwarding Enabled', config('erp-agent.logging.forwarding_enabled', true) ? 'Yes' : 'No'],
                ['Forward Interval', config('erp-agent.logging.forward_interval', 300) . 's'],
                ['Batch Size', LogForwarder::BATCH_SIZE],
                ['Max Queue Size', LogForwarder::MAX_QUEUE_SIZE],
            ]
        );
    }
}
