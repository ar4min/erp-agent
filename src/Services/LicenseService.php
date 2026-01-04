<?php

namespace Ar4min\ErpAgent\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    protected ControlPlaneClient $client;

    const CACHE_KEY = 'erp_license_validation';
    const LAST_VALID_KEY = 'erp_license_last_valid';
    const GRACE_PERIOD_KEY = 'erp_license_grace_start';

    public function __construct(ControlPlaneClient $client)
    {
        $this->client = $client;
    }

    /**
     * Check if license is currently valid
     */
    public function isValid(): bool
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached !== null) {
            return $cached['valid'] ?? false;
        }

        $result = $this->validate();
        return $result['valid'] ?? false;
    }

    /**
     * Validate license with Control Plane
     */
    public function validate(): array
    {
        $result = $this->client->validateLicense();

        if (isset($result['valid']) && $result['valid']) {
            $this->cacheValidation($result);
            $this->clearGracePeriod();

            Log::info('[ERP Agent] License validated successfully');
            return $result;
        }

        // Check if we're offline
        if (isset($result['offline']) && $result['offline']) {
            return $this->handleOfflineValidation();
        }

        Log::warning('[ERP Agent] License validation failed', $result);
        return $result;
    }

    /**
     * Handle validation when Control Plane is unreachable
     */
    protected function handleOfflineValidation(): array
    {
        $lastValid = Cache::get(self::LAST_VALID_KEY);
        $graceStart = Cache::get(self::GRACE_PERIOD_KEY);
        $gracePeriod = config('erp-agent.license.grace_period', 72 * 60 * 60);

        if ($lastValid) {
            // Start grace period if not already started
            if (!$graceStart) {
                $graceStart = now();
                Cache::forever(self::GRACE_PERIOD_KEY, $graceStart);
                Log::info('[ERP Agent] Starting license grace period');
            }

            // Check if grace period has expired
            $graceRemaining = $gracePeriod - now()->diffInSeconds($graceStart);

            if ($graceRemaining > 0) {
                Log::info('[ERP Agent] Operating in grace period', [
                    'remaining_seconds' => $graceRemaining,
                    'remaining_hours' => round($graceRemaining / 3600, 1),
                ]);

                return [
                    'valid' => true,
                    'grace_period' => true,
                    'grace_remaining' => $graceRemaining,
                    'modules' => $lastValid['modules'] ?? [],
                ];
            }

            Log::error('[ERP Agent] License grace period expired');
            return [
                'valid' => false,
                'error' => 'Grace period expired',
            ];
        }

        return [
            'valid' => false,
            'error' => 'Cannot validate license and no previous validation found',
        ];
    }

    /**
     * Cache successful validation result
     */
    protected function cacheValidation(array $result): void
    {
        $cacheTtl = config('erp-agent.license.cache_ttl', 24 * 60 * 60);

        Cache::put(self::CACHE_KEY, $result, $cacheTtl);
        Cache::forever(self::LAST_VALID_KEY, $result);
    }

    /**
     * Clear grace period tracking
     */
    protected function clearGracePeriod(): void
    {
        Cache::forget(self::GRACE_PERIOD_KEY);
    }

    /**
     * Get enabled modules for this license
     */
    public function getModules(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        return $cached['modules'] ?? [];
    }

    /**
     * Check if a specific module is enabled
     */
    public function hasModule(string $module): bool
    {
        return in_array($module, $this->getModules());
    }

    /**
     * Get license expiration date
     */
    public function getExpiresAt(): ?string
    {
        $cached = Cache::get(self::CACHE_KEY);
        return $cached['expires_at'] ?? null;
    }

    /**
     * Get days until expiration
     */
    public function getDaysUntilExpiration(): ?int
    {
        $cached = Cache::get(self::CACHE_KEY);
        return $cached['days_until_expiration'] ?? null;
    }

    /**
     * Force refresh license validation
     */
    public function refresh(): array
    {
        Cache::forget(self::CACHE_KEY);
        return $this->validate();
    }

    /**
     * Get comprehensive license status
     */
    public function getStatus(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        $graceStart = Cache::get(self::GRACE_PERIOD_KEY);
        $gracePeriod = config('erp-agent.license.grace_period', 72 * 60 * 60);

        return [
            'valid' => $cached['valid'] ?? false,
            'modules' => $cached['modules'] ?? [],
            'expires_at' => $cached['expires_at'] ?? null,
            'days_until_expiration' => $cached['days_until_expiration'] ?? null,
            'tenant_name' => $cached['tenant_name'] ?? null,
            'plan_name' => $cached['plan_name'] ?? null,
            'in_grace_period' => $graceStart !== null,
            'grace_remaining' => $graceStart
                ? max(0, $gracePeriod - now()->diffInSeconds($graceStart))
                : null,
            'last_validated' => $cached['validated_at'] ?? null,
        ];
    }

    /**
     * Check if currently in grace period
     */
    public function isInGracePeriod(): bool
    {
        return Cache::get(self::GRACE_PERIOD_KEY) !== null;
    }

    /**
     * Get remaining grace period in seconds
     */
    public function getGraceRemaining(): ?int
    {
        $graceStart = Cache::get(self::GRACE_PERIOD_KEY);

        if (!$graceStart) {
            return null;
        }

        $gracePeriod = config('erp-agent.license.grace_period', 72 * 60 * 60);
        return max(0, $gracePeriod - now()->diffInSeconds($graceStart));
    }
}
