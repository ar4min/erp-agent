<?php

namespace Ar4min\ErpAgent\Commands;

use Ar4min\ErpAgent\Services\LicenseService;
use Illuminate\Console\Command;

class LicenseCommand extends Command
{
    protected $signature = 'erp:license
                            {--refresh : Force refresh validation from Control Plane}
                            {--status : Show detailed license status}';

    protected $description = 'Validate or check license status';

    public function handle(LicenseService $licenseService): int
    {
        if ($this->option('status')) {
            return $this->showStatus($licenseService);
        }

        if ($this->option('refresh')) {
            $this->info('Force refreshing license...');
            $result = $licenseService->refresh();
        } else {
            $this->info('Validating license...');
            $result = $licenseService->validate();
        }

        if ($result['valid'] ?? false) {
            $this->info('✓ License is valid');

            if ($result['grace_period'] ?? false) {
                $remaining = $result['grace_remaining'] ?? 0;
                $hours = floor($remaining / 3600);
                $this->warn("⚠ Operating in grace period ({$hours} hours remaining)");
            }

            // Show tenant/plan info
            if (isset($result['tenant_name'])) {
                $this->line("  Tenant: {$result['tenant_name']}");
            }
            if (isset($result['plan_name'])) {
                $this->line("  Plan: {$result['plan_name']}");
            }

            // Show modules
            if (!empty($result['modules'])) {
                $this->newLine();
                $this->info('Enabled modules:');
                foreach ($result['modules'] as $module) {
                    $this->line("  • {$module}");
                }
            }

            // Show expiration
            if ($result['expires_at'] ?? null) {
                $days = $result['days_until_expiration'] ?? '?';
                $this->newLine();
                $this->info("Expires: {$result['expires_at']} ({$days} days)");
            }

            return self::SUCCESS;
        }

        $this->error('✗ License is invalid');
        $this->error('Error: ' . ($result['error'] ?? 'Unknown error'));
        return self::FAILURE;
    }

    protected function showStatus(LicenseService $licenseService): int
    {
        $status = $licenseService->getStatus();

        $this->newLine();
        $this->info('┌─────────────────────────────────────────┐');
        $this->info('│           LICENSE STATUS                │');
        $this->info('└─────────────────────────────────────────┘');
        $this->newLine();

        $this->table([], [
            ['Status', $status['valid'] ? '<fg=green>✓ Valid</>' : '<fg=red>✗ Invalid</>'],
            ['Tenant', $status['tenant_name'] ?? 'N/A'],
            ['Plan', $status['plan_name'] ?? 'N/A'],
            ['Modules', implode(', ', $status['modules']) ?: 'None'],
            ['Expires At', $status['expires_at'] ?? 'N/A'],
            ['Days Remaining', $status['days_until_expiration'] ?? 'N/A'],
            ['Grace Period', $status['in_grace_period'] ? 'Yes' : 'No'],
            ['Grace Remaining', $status['grace_remaining'] ? round($status['grace_remaining'] / 3600, 1) . ' hours' : 'N/A'],
            ['Last Validated', $status['last_validated'] ?? 'Never'],
        ]);

        return self::SUCCESS;
    }
}
