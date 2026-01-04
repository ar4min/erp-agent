<?php

namespace Ar4min\ErpAgent\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'erp:install
                            {--url= : Control Plane URL}
                            {--token= : Service Token}
                            {--instance= : Instance ID}
                            {--license= : License Key}';

    protected $description = 'Install and configure ERP Agent';

    public function handle(): int
    {
        $this->info('');
        $this->info('  ███████╗██████╗ ██████╗      █████╗  ██████╗ ███████╗███╗   ██╗████████╗');
        $this->info('  ██╔════╝██╔══██╗██╔══██╗    ██╔══██╗██╔════╝ ██╔════╝████╗  ██║╚══██╔══╝');
        $this->info('  █████╗  ██████╔╝██████╔╝    ███████║██║  ███╗█████╗  ██╔██╗ ██║   ██║   ');
        $this->info('  ██╔══╝  ██╔══██╗██╔═══╝     ██╔══██║██║   ██║██╔══╝  ██║╚██╗██║   ██║   ');
        $this->info('  ███████╗██║  ██║██║         ██║  ██║╚██████╔╝███████╗██║ ╚████║   ██║   ');
        $this->info('  ╚══════╝╚═╝  ╚═╝╚═╝         ╚═╝  ╚═╝ ╚═════╝ ╚══════╝╚═╝  ╚═══╝   ╚═╝   ');
        $this->info('');

        // Publish config
        $this->info('Publishing configuration...');
        $this->call('vendor:publish', [
            '--tag' => 'erp-agent-config',
            '--force' => true,
        ]);

        // Get configuration values
        $url = $this->option('url') ?? $this->ask('Control Plane URL', 'http://localhost:8001');
        $token = $this->option('token') ?? $this->ask('Service Token');
        $instanceId = $this->option('instance') ?? $this->ask('Instance ID');
        $licenseKey = $this->option('license') ?? $this->ask('License Key');
        $machineId = Str::uuid()->toString();

        // Update .env file
        $this->updateEnvFile([
            'CONTROL_PLANE_URL' => $url,
            'CONTROL_PLANE_TOKEN' => $token,
            'INSTANCE_ID' => $instanceId,
            'LICENSE_KEY' => $licenseKey,
            'MACHINE_ID' => $machineId,
        ]);

        $this->info('');
        $this->info('✓ Configuration saved to .env');
        $this->info('');

        // Test connection
        if ($this->confirm('Would you like to test the connection?', true)) {
            $this->call('erp:test-connection');
        }

        $this->info('');
        $this->info('Installation complete! Next steps:');
        $this->line('');
        $this->line('  1. Add to your scheduler (app/Console/Kernel.php):');
        $this->line('     $schedule->command("erp:heartbeat --once")->everyMinute();');
        $this->line('     $schedule->command("erp:license --refresh")->everySixHours();');
        $this->line('');
        $this->line('  2. Add middleware to routes (if needed):');
        $this->line('     Route::middleware(["erp.license"])->group(function () { ... });');
        $this->line('');

        return self::SUCCESS;
    }

    protected function updateEnvFile(array $values): void
    {
        $envPath = base_path('.env');
        $envContent = file_exists($envPath) ? file_get_contents($envPath) : '';

        foreach ($values as $key => $value) {
            $pattern = "/^{$key}=.*/m";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, "{$key}={$value}", $envContent);
            } else {
                $envContent .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envPath, $envContent);
    }
}
