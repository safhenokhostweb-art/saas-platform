<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;

class DriftScanCommand extends Command
{
    protected $signature = 'drift:scan
        {--ci : CI mode — fail on high-severity violations}
        {--report : Generate detailed drift report}
        {--blades-only : Only check Blade file duplication}
        {--controllers-only : Only check controller duplication}
        {--ownership-only : Only check ownership violations}
        {--contracts-only : Only check contract violations}';

    protected $description = 'Scan for architectural drift: duplicate files, ownership violations, contract mismatches';

    public function handle(): int
    {
        $scriptPath = base_path('scripts/drift-detect.php');

        if (!file_exists($scriptPath)) {
            $this->error('Drift detection script not found at: ' . $scriptPath);
            $this->line('Run: php artisan vendor:publish --tag=core-utils-scripts');
            return 1;
        }

        $args = [];

        if ($this->option('ci')) {
            $args[] = '--ci';
        }
        if ($this->option('report')) {
            $args[] = '--report';
        }
        if ($this->option('blades-only')) {
            $args[] = '--check-blade';
        }
        if ($this->option('controllers-only')) {
            $args[] = '--check-controllers';
        }
        if ($this->option('ownership-only')) {
            $args[] = '--check-ownership';
        }
        if ($this->option('contracts-only')) {
            $args[] = '--check-contracts';
        }

        $command = sprintf(
            '%s %s %s 2>&1',
            PHP_BINARY,
            escapeshellarg($scriptPath),
            implode(' ', array_map('escapeshellarg', $args))
        );

        $this->line(' Running drift scan...');
        $this->line('');

        passthru($command, $exitCode);

        return $exitCode;
    }
}
