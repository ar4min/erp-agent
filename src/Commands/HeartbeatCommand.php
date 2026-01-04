<?php

namespace Ar4min\ErpAgent\Commands;

use Ar4min\ErpAgent\Services\HeartbeatService;
use Illuminate\Console\Command;

class HeartbeatCommand extends Command
{
    protected $signature = 'erp:heartbeat
                            {--once : Send only once instead of continuously}
                            {--show : Show metrics without sending}';

    protected $description = 'Send heartbeat to Control Plane';

    public function handle(HeartbeatService $heartbeatService): int
    {
        if (!config('erp-agent.heartbeat.enabled')) {
            $this->warn('Heartbeat is disabled in configuration.');
            return self::SUCCESS;
        }

        if ($this->option('show')) {
            return $this->showMetrics($heartbeatService);
        }

        if ($this->option('once')) {
            return $this->sendOnce($heartbeatService);
        }

        return $this->sendContinuously($heartbeatService);
    }

    protected function showMetrics(HeartbeatService $heartbeatService): int
    {
        $metrics = $heartbeatService->collectMetrics();

        $this->info('Current Metrics:');
        $this->table(
            ['Metric', 'Value'],
            collect($metrics)->map(fn($v, $k) => [$k, is_array($v) ? json_encode($v) : $v])->toArray()
        );

        return self::SUCCESS;
    }

    protected function sendOnce(HeartbeatService $heartbeatService): int
    {
        $this->info('Sending heartbeat...');

        $metrics = $heartbeatService->collectMetrics();
        $this->table(
            ['Metric', 'Value'],
            collect($metrics)->map(fn($v, $k) => [$k, is_array($v) ? json_encode($v) : $v])->toArray()
        );

        $result = $heartbeatService->send();

        if ($result['success'] ?? false) {
            $this->info('✓ Heartbeat sent successfully');
            return self::SUCCESS;
        }

        $this->error('✗ Heartbeat failed: ' . ($result['error'] ?? 'Unknown error'));
        return self::FAILURE;
    }

    protected function sendContinuously(HeartbeatService $heartbeatService): int
    {
        $interval = config('erp-agent.heartbeat.interval', 60);

        $this->info("Starting heartbeat service (interval: {$interval}s)");
        $this->info('Press Ctrl+C to stop');
        $this->newLine();

        $count = 0;
        while (true) {
            $count++;
            $result = $heartbeatService->send();

            $status = ($result['success'] ?? false) ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $message = ($result['success'] ?? false) ? 'sent' : 'failed';

            $this->line(sprintf(
                '[%s] %s Heartbeat #%d %s',
                now()->format('Y-m-d H:i:s'),
                $status,
                $count,
                $message
            ));

            sleep($interval);
        }
    }
}
