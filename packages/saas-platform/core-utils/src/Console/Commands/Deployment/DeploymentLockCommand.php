<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Deployment;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseLockManager;

class DeploymentLockCommand extends Command
{
    protected $signature = 'deployment:lock
                            {--ttl=3600 : Lock TTL in seconds (default 1 hour)}
                            {--force   : Override existing lock}';

    protected $description = 'Acquire a deployment lock for the current environment';

    public function handle(ReleaseLockManager $lockManager): int
    {
        if ($lockManager->hasActiveLock() && !$this->option('force')) {
            $lock = $lockManager->getActiveLock();
            $this->error("Deployment lock already held by: {$lock?->owner} (UUID: {$lock?->deployment_uuid})");
            $this->line("  Use --force to override, or run: php artisan deployment:unlock {$lock?->deployment_uuid}");
            return 1;
        }

        if ($this->option('force')) {
            $released = $lockManager->forceUnlockAll();
            $this->warn("Force-released {$released} existing lock(s).");
        }

        $uuid = Str::uuid()->toString();
        $ttl  = (int) $this->option('ttl');

        if ($lockManager->acquire($uuid, $ttl)) {
            $this->info("Deployment lock acquired.");
            $this->line("  UUID:    {$uuid}");
            $this->line("  TTL:     {$ttl}s");
            $this->line("  Env:     " . config('app.env', 'production'));
            return 0;
        }

        $this->error('Failed to acquire deployment lock.');
        return 1;
    }
}
