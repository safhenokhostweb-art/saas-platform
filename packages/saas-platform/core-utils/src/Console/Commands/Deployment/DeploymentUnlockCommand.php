<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Deployment;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseLockManager;

class DeploymentUnlockCommand extends Command
{
    protected $signature = 'deployment:unlock
                            {uuid?       : Specific deployment UUID to unlock}
                            {--all       : Force-unlock all locks for this environment}
                            {--force     : Skip confirmation prompt}';

    protected $description = 'Release a deployment lock (emergency use)';

    public function handle(ReleaseLockManager $lockManager): int
    {
        if ($this->option('all')) {
            if (!$this->option('force') && !$this->confirm('Force-unlock ALL deployment locks? This is an emergency operation.')) {
                $this->line('Aborted.');
                return 0;
            }

            $count = $lockManager->forceUnlockAll();
            $this->warn("Force-released {$count} deployment lock(s).");
            return 0;
        }

        $uuid = $this->argument('uuid');

        if (!$uuid) {
            $lock = $lockManager->getActiveLock();
            if (!$lock) {
                $this->info('No active deployment lock found.');
                return 0;
            }
            $uuid = $lock->deployment_uuid;
            $this->line("Active lock UUID: {$uuid} (owner: {$lock->owner})");
        }

        if (!$this->option('force') && !$this->confirm("Release deployment lock {$uuid}?")) {
            $this->line('Aborted.');
            return 0;
        }

        if ($lockManager->forceUnlock($uuid)) {
            $this->info("Lock {$uuid} released.");
            return 0;
        }

        $this->error("Lock {$uuid} not found or already released.");
        return 1;
    }
}
