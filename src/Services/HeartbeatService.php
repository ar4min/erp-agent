<?php

namespace Ar4min\ErpAgent\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HeartbeatService
{
    protected ControlPlaneClient $client;

    public function __construct(ControlPlaneClient $client)
    {
        $this->client = $client;
    }

    /**
     * Send heartbeat with current system metrics
     */
    public function send(): array
    {
        if (!config('erp-agent.heartbeat.enabled')) {
            return ['success' => false, 'error' => 'Heartbeat disabled'];
        }

        return $this->client->sendHeartbeat($this->collectMetrics());
    }

    /**
     * Collect all system metrics
     */
    public function collectMetrics(): array
    {
        return [
            'version' => config('app.version', '1.0.0'),
            'uptime' => $this->getUptime(),
            'license_status' => $this->getLicenseStatus(),
            'license_expires_in_days' => $this->getLicenseDaysRemaining(),
            'metrics' => [
                'active_users' => $this->getActiveUsers(),
                'queue_pending' => $this->getQueueSize(),
                'failed_jobs' => $this->getFailedJobs(),
                'disk_usage_percent' => $this->getDiskUsage(),
                'memory_usage_mb' => $this->getMemoryUsage(),
                'cpu_load' => $this->getCpuLoad(),
                'avg_response_time_ms' => $this->getAverageResponseTime(),
            ],
        ];
    }

    /**
     * Get system uptime in seconds
     */
    public function getUptime(): int
    {
        $cacheKey = 'erp_agent_start_time';
        $startTime = Cache::get($cacheKey);

        if (!$startTime) {
            $startTime = now();
            Cache::forever($cacheKey, $startTime);
        }

        return max(0, now()->diffInSeconds($startTime));
    }

    /**
     * Get count of active users (logged in within last 15 minutes)
     */
    public function getActiveUsers(): int
    {
        try {
            if (DB::getSchemaBuilder()->hasTable('sessions')) {
                return DB::table('sessions')
                    ->where('last_activity', '>', now()->subMinutes(15)->timestamp)
                    ->whereNotNull('user_id')
                    ->count();
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return 0;
    }

    /**
     * Get pending jobs count
     */
    public function getQueueSize(): int
    {
        try {
            if (DB::getSchemaBuilder()->hasTable('jobs')) {
                return DB::table('jobs')->count();
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return 0;
    }

    /**
     * Get failed jobs count
     */
    public function getFailedJobs(): int
    {
        try {
            if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                return DB::table('failed_jobs')->count();
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return 0;
    }

    /**
     * Get disk usage percentage
     */
    public function getDiskUsage(): float
    {
        $total = @disk_total_space(base_path());
        $free = @disk_free_space(base_path());

        if ($total > 0) {
            return round((($total - $free) / $total) * 100, 2);
        }

        return 0;
    }

    /**
     * Get memory usage in MB
     */
    public function getMemoryUsage(): float
    {
        return round(memory_get_usage(true) / 1024 / 1024, 2);
    }

    /**
     * Get CPU load average
     */
    public function getCpuLoad(): float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0] ?? 0;
        }

        return 0;
    }

    /**
     * Get average response time (from cache if tracked)
     */
    public function getAverageResponseTime(): float
    {
        return Cache::get('erp_agent_avg_response_time', 0);
    }

    /**
     * Get license status from cache
     */
    protected function getLicenseStatus(): string
    {
        $cached = Cache::get('erp_license_validation');
        return ($cached['valid'] ?? false) ? 'valid' : 'invalid';
    }

    /**
     * Get license days remaining from cache
     */
    protected function getLicenseDaysRemaining(): int
    {
        $cached = Cache::get('erp_license_validation');
        return $cached['days_until_expiration'] ?? 0;
    }

    /**
     * Record response time for averaging
     */
    public static function recordResponseTime(float $ms): void
    {
        $key = 'erp_agent_response_times';
        $times = Cache::get($key, []);

        $times[] = $ms;

        // Keep last 100 measurements
        if (count($times) > 100) {
            $times = array_slice($times, -100);
        }

        Cache::put($key, $times, 3600);

        // Update average
        $avg = count($times) > 0 ? array_sum($times) / count($times) : 0;
        Cache::put('erp_agent_avg_response_time', round($avg, 2), 3600);
    }
}
