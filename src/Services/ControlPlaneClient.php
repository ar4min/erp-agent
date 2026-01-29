<?php

namespace Ar4min\ErpAgent\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\PendingRequest;

class ControlPlaneClient
{
    protected string $baseUrl;
    protected string $serviceToken;
    protected string $instanceId;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('erp-agent.control_plane.url'), '/');
        $this->serviceToken = config('erp-agent.control_plane.token');
        $this->instanceId = config('erp-agent.instance.id');
        $this->timeout = config('erp-agent.control_plane.timeout', 30);
    }

    /**
     * Get configured HTTP client
     */
    protected function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->serviceToken,
                'X-Instance-ID' => $this->instanceId,
                'Accept' => 'application/json',
            ])
            ->timeout($this->timeout);
    }

    /**
     * Send heartbeat to Control Plane
     */
    public function sendHeartbeat(array $metrics): array
    {
        try {
            $payload = array_merge(['instance_id' => $this->instanceId], $metrics);
            $response = $this->request()->post('/api/agent/heartbeat', $payload);

            if ($response->successful()) {
                Log::debug('[ERP Agent] Heartbeat sent successfully');
                return array_merge($response->json(), ['success' => true]);
            }

            Log::warning('[ERP Agent] Heartbeat failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['success' => false, 'error' => $response->body()];
        } catch (\Exception $e) {
            Log::error('[ERP Agent] Heartbeat exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Validate license with Control Plane
     */
    public function validateLicense(): array
    {
        try {
            $response = $this->request()->post('/api/license/validate', [
                'license_key' => config('erp-agent.license.key'),
                'instance_id' => $this->instanceId,
                'machine_id' => config('erp-agent.instance.machine_id'),
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'valid' => false,
                'error' => 'Validation request failed',
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('[ERP Agent] License validation exception', ['error' => $e->getMessage()]);
            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'offline' => true,
            ];
        }
    }

    /**
     * Get configuration from Control Plane
     */
    public function getConfig(): ?array
    {
        try {
            $response = $this->request()->get('/api/config');
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('[ERP Agent] Config fetch exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Check for updates
     */
    public function checkUpdates(string $currentVersion): ?array
    {
        try {
            $response = $this->request()->get('/api/updates/check', [
                'current_version' => $currentVersion,
            ]);
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('[ERP Agent] Update check exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Forward logs to Control Plane
     */
    public function forwardLogs(array $logs): bool
    {
        try {
            $response = $this->request()->post('/api/agent/logs', [
                'logs' => $logs,
            ]);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('[ERP Agent] Log forwarding exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Test connection to Control Plane
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->request()->get('/api/agent/health');
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get instance ID
     */
    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    /**
     * Get base URL
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
