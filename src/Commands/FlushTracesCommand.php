<?php

namespace Ar4min\ErpAgent\Commands;

use Ar4min\ErpAgent\Services\RequestTracerService;
use Illuminate\Console\Command;

class FlushTracesCommand extends Command
{
    protected $signature = 'erp:flush-traces
        {--status : Show queue status}
        {--clear : Clear queue without sending}
        {--daemon : Run continuously}';

    protected $description = 'Flush queued request traces to Control Plane';

    public function handle(RequestTracerService $tracer): int
    {
        if ($this->option('status')) {
            $this->info("Traces in queue: {$tracer->queueSize()}");
            return 0;
        }

        if ($this->option('clear')) {
            $tracer->clearQueue();
            $this->info('Trace queue cleared.');
            return 0;
        }

        if ($this->option('daemon')) {
            $interval = config('erp-agent.tracing.flush_interval', 60);
            $this->info("Running trace flush daemon (interval: {$interval}s)...");

            while (true) {
                $result = $tracer->flush();
                if ($result['sent'] > 0 || $result['failed'] > 0) {
                    $this->line("[" . now()->format('H:i:s') . "] Sent: {$result['sent']}, Failed: {$result['failed']}, Remaining: {$result['remaining']}");
                }
                sleep($interval);
            }
        }

        // Single flush
        $result = $tracer->flush();
        $this->info("Sent: {$result['sent']}, Failed: {$result['failed']}, Remaining: {$result['remaining']}");

        return 0;
    }
}
