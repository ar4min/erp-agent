<?php

namespace Ar4min\ErpAgent\Commands;

use Ar4min\ErpAgent\Services\ControlPlaneClient;
use Ar4min\ErpAgent\Services\HeartbeatService;
use Ar4min\ErpAgent\Services\LicenseService;
use Illuminate\Console\Command;

class TestConnectionCommand extends Command
{
    protected $signature = 'erp:test-connection';

    protected $description = 'Test connection to Control Plane';

    public function handle(
        ControlPlaneClient $client,
        HeartbeatService $heartbeatService,
        LicenseService $licenseService
    ): int {
        $this->newLine();
        $this->info('┌─────────────────────────────────────────┐');
        $this->info('│      CONTROL PLANE CONNECTION TEST      │');
        $this->info('└─────────────────────────────────────────┘');
        $this->newLine();

        // Show configuration
        $this->info('Configuration:');
        $this->table([], [
            ['Control Plane URL', config('erp-agent.control_plane.url')],
            ['Instance ID', config('erp-agent.instance.id') ?: '<fg=yellow>(not set)</>'],
            ['License Key', $this->maskKey(config('erp-agent.license.key'))],
            ['Service Token', config('erp-agent.control_plane.token') ? '<fg=green>***</>' : '<fg=yellow>(not set)</>'],
            ['Machine ID', config('erp-agent.instance.machine_id') ?: '<fg=yellow>(not set)</>'],
        ]);

        $this->newLine();
        $allPassed = true;

        // Test 1: Connection
        $this->info('Testing connection...');
        if ($client->testConnection()) {
            $this->line('  <fg=green>✓</> Control Plane is reachable');
        } else {
            $this->line('  <fg=red>✗</> Cannot reach Control Plane');
            $this->warn('    Make sure Control Plane is running and accessible.');
            $allPassed = false;
        }

        $this->newLine();

        // Test 2: Heartbeat
        $this->info('Testing heartbeat...');
        $heartbeatResult = $heartbeatService->send();
        if ($heartbeatResult['success'] ?? false) {
            $this->line('  <fg=green>✓</> Heartbeat sent successfully');
        } else {
            $this->line('  <fg=red>✗</> Heartbeat failed');
            $this->line('    Error: ' . ($heartbeatResult['error'] ?? 'Unknown'));
            $allPassed = false;
        }

        $this->newLine();

        // Test 3: License
        $this->info('Testing license validation...');
        $licenseResult = $licenseService->validate();
        if ($licenseResult['valid'] ?? false) {
            $this->line('  <fg=green>✓</> License is valid');
            if (!empty($licenseResult['modules'])) {
                $this->line('    Modules: ' . implode(', ', $licenseResult['modules']));
            }
            if ($licenseResult['grace_period'] ?? false) {
                $hours = round(($licenseResult['grace_remaining'] ?? 0) / 3600, 1);
                $this->line("    <fg=yellow>⚠ In grace period ({$hours}h remaining)</>");
            }
        } else {
            $this->line('  <fg=red>✗</> License validation failed');
            $this->line('    Error: ' . ($licenseResult['error'] ?? 'Unknown'));
            $allPassed = false;
        }

        $this->newLine();

        if ($allPassed) {
            $this->info('┌─────────────────────────────────────────┐');
            $this->info('│  <fg=green>All tests passed!</>                       │');
            $this->info('└─────────────────────────────────────────┘');
        } else {
            $this->error('┌─────────────────────────────────────────┐');
            $this->error('│  Some tests failed. Check configuration │');
            $this->error('└─────────────────────────────────────────┘');
        }

        $this->newLine();

        return $allPassed ? self::SUCCESS : self::FAILURE;
    }

    protected function maskKey(?string $key): string
    {
        if (!$key) {
            return '<fg=yellow>(not set)</>';
        }

        if (strlen($key) > 10) {
            return substr($key, 0, 10) . '...';
        }

        return $key;
    }
}
