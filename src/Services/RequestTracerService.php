<?php

namespace Ar4min\ErpAgent\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RequestTracerService
{
    protected ControlPlaneClient $client;
    protected int $batchSize = 50;
    protected int $maxQueueSize = 2000;

    protected static string $cacheKey = 'erp_agent_trace_queue';

    public function __construct(ControlPlaneClient $client)
    {
        $this->client = $client;
        $this->batchSize = config('erp-agent.tracing.batch_size', 50);
        $this->maxQueueSize = config('erp-agent.tracing.max_queue_size', 2000);
    }

    /**
     * Queue a trace for sending to Control Plane
     */
    public function queue(array $trace): void
    {
        if (!config('erp-agent.tracing.enabled', true)) {
            return;
        }

        $queue = Cache::get(static::$cacheKey, []);

        // Drop oldest if queue is full
        if (count($queue) >= $this->maxQueueSize) {
            $queue = array_slice($queue, -($this->maxQueueSize - 1));
        }

        $queue[] = $trace;
        Cache::put(static::$cacheKey, $queue, now()->addHours(2));

        // Auto-flush when batch size reached
        if (count($queue) >= $this->batchSize) {
            $this->flush();
        }
    }

    /**
     * Flush queued traces to Control Plane
     */
    public function flush(): array
    {
        $queue = Cache::get(static::$cacheKey, []);

        if (empty($queue)) {
            return ['sent' => 0, 'failed' => 0, 'remaining' => 0];
        }

        $sent = 0;
        $failed = 0;
        $remaining = [];

        // Send in batches
        foreach (array_chunk($queue, $this->batchSize) as $batch) {
            if ($this->sendBatch($batch)) {
                $sent += count($batch);
            } else {
                $failed += count($batch);
                $remaining = array_merge($remaining, $batch);
            }
        }

        // Keep failed entries for retry
        Cache::put(static::$cacheKey, $remaining, now()->addHours(2));

        Log::debug('[ERP Agent] Traces flushed', [
            'sent' => $sent,
            'failed' => $failed,
            'remaining' => count($remaining),
        ]);

        return [
            'sent' => $sent,
            'failed' => $failed,
            'remaining' => count($remaining),
        ];
    }

    /**
     * Send a batch of traces to Control Plane
     */
    protected function sendBatch(array $traces): bool
    {
        return $this->client->forwardTraces($traces);
    }

    /**
     * Get current queue size
     */
    public function queueSize(): int
    {
        return count(Cache::get(static::$cacheKey, []));
    }

    /**
     * Clear queue
     */
    public function clearQueue(): void
    {
        Cache::forget(static::$cacheKey);
    }
}
