<?php

namespace Ar4min\ErpAgent\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LogForwarder
{
    protected ControlPlaneClient $client;

    const QUEUE_KEY = 'erp_agent_log_queue';
    const BATCH_SIZE = 100;
    const MAX_QUEUE_SIZE = 5000;

    public function __construct(ControlPlaneClient $client)
    {
        $this->client = $client;
    }

    /**
     * Add a log entry to the queue
     */
    public function queue(string $level, string $category, string $message, array $context = []): void
    {
        if (!config('erp-agent.logging.forwarding_enabled', true)) {
            return;
        }

        $entry = [
            'timestamp' => now()->toIso8601String(),
            'level' => strtoupper($level),
            'category' => $category,
            'message' => $message,
            'context' => $context,
        ];

        $queue = Cache::get(self::QUEUE_KEY, []);

        // Prevent queue from growing too large
        if (count($queue) >= self::MAX_QUEUE_SIZE) {
            // Drop oldest entries
            $queue = array_slice($queue, self::BATCH_SIZE);
        }

        $queue[] = $entry;
        Cache::put(self::QUEUE_KEY, $queue, 86400); // 24h TTL
    }

    /**
     * Add a technical log
     */
    public function technical(string $level, string $message, array $context = []): void
    {
        $this->queue($level, 'technical', $message, $context);
    }

    /**
     * Add a security log
     */
    public function security(string $level, string $message, array $context = []): void
    {
        $this->queue($level, 'security', $message, $context);
    }

    /**
     * Add an audit log
     */
    public function audit(string $level, string $message, array $context = []): void
    {
        $this->queue($level, 'audit', $message, $context);
    }

    /**
     * Flush queued logs to Control Plane in batches
     */
    public function flush(): array
    {
        $queue = Cache::get(self::QUEUE_KEY, []);

        if (empty($queue)) {
            return ['sent' => 0, 'failed' => 0, 'remaining' => 0];
        }

        $sent = 0;
        $failed = 0;
        $batches = array_chunk($queue, self::BATCH_SIZE);
        $failedEntries = [];

        foreach ($batches as $batch) {
            try {
                $success = $this->client->forwardLogs($batch);

                if ($success) {
                    $sent += count($batch);
                } else {
                    $failed += count($batch);
                    $failedEntries = array_merge($failedEntries, $batch);
                }
            } catch (\Exception $e) {
                Log::warning('[ERP Agent] Log forwarding batch failed', [
                    'error' => $e->getMessage(),
                    'batch_size' => count($batch),
                ]);
                $failed += count($batch);
                $failedEntries = array_merge($failedEntries, $batch);
            }
        }

        // Keep failed entries in queue for retry
        if (!empty($failedEntries)) {
            Cache::put(self::QUEUE_KEY, $failedEntries, 86400);
        } else {
            Cache::forget(self::QUEUE_KEY);
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'remaining' => count($failedEntries),
        ];
    }

    /**
     * Get current queue size
     */
    public function queueSize(): int
    {
        return count(Cache::get(self::QUEUE_KEY, []));
    }

    /**
     * Clear the log queue
     */
    public function clearQueue(): void
    {
        Cache::forget(self::QUEUE_KEY);
    }

    /**
     * Detect log category from message/context
     */
    public static function detectCategory(string $message, array $context = []): string
    {
        $lower = mb_strtolower($message);

        // Security patterns
        $securityPatterns = [
            'login', 'logout', 'authentication', 'unauthorized',
            'forbidden', 'permission', 'password', 'token',
            'suspicious', 'blocked', 'brute', 'injection',
            'xss', 'csrf', 'security',
        ];

        foreach ($securityPatterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                return 'security';
            }
        }

        // Audit patterns
        $auditPatterns = [
            'created', 'updated', 'deleted', 'modified',
            'user action', 'data change', 'config change',
            'export', 'import', 'audit',
        ];

        foreach ($auditPatterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                return 'audit';
            }
        }

        // Default: technical
        return 'technical';
    }
}
