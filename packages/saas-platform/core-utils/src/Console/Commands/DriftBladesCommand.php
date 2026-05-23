<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;

class DriftBladesCommand extends Command
{
    protected $signature = 'drift:blades
        {--ci : CI mode — fail on high-severity violations}
        {--report : Generate detailed drift report}';

    protected $description = 'Scan for duplicate Blade files between saas-platform and customer-site';

    public function handle(): int
    {
        $scriptPath = base_path('scripts/drift-detect.php');

        if (!file_exists($scriptPath)) {
            $this->error('Drift detection script not found at: ' . $scriptPath);
            return 1;
        }

        $args = ['--check-blade'];
        if ($this->option('ci')) {
            $args[] = '--ci';
        }
        if ($this->option('report')) {
            $args[] = '--report';
        }

        $command = sprintf(
            '%s %s %s 2>&1',
            PHP_BINARY,
            escapeshellarg($scriptPath),
            implode(' ', array_map('escapeshellarg', $args))
        );

        $this->line(' Scanning for duplicate Blade files...');
        $this->line('');

        passthru($command, $exitCode);

        return $exitCode;
    }
}
