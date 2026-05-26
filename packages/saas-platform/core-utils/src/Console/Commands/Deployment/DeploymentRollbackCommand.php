<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Deployment;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Services\Rollback\RollbackCoordinator;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseManifestManager;

class DeploymentRollbackCommand extends Command
{
    protected $signature = 'deployment:rollback
                            {release?   : Target release UUID (omit for previous release)}
                            {--dry-run  : Preview rollback plan without executing}
                            {--force    : Skip confirmation}
                            {--json     : Output as JSON}';

    protected $description = 'Roll back to a previous deployment release (Phase 8.2)';

    public function handle(
        RollbackCoordinator    $coordinator,
        ReleaseManifestManager $manifestManager
    ): int {
        $targetUuid = $this->argument('release');

        // Auto-resolve previous release
        if (!$targetUuid) {
            $all  = $manifestManager->all();
            $keys = array_keys($all);

            if (count($keys) < 2) {
                $this->error('No previous release found to roll back to.');
                return 1;
            }

            $targetUuid = $keys[1]; // second newest
        }

        $target = $manifestManager->read($targetUuid);

        if (!$target) {
            $this->error("Release manifest not found: {$targetUuid}");
            return 1;
        }

        $dryRun = $this->option('dry-run');

        if (!$dryRun && !$this->option('force')) {
            $this->warn("You are about to roll back to: {$targetUuid}");
            $this->line("  Released at: {$target['released_at']}");
            $this->line("  Git commit:  " . ($target['git_commit'] ?? 'unknown'));

            if (!$this->confirm('Proceed with rollback?')) {
                $this->line('Aborted.');
                return 0;
            }
        }

        $result = $coordinator->rollback($targetUuid, $dryRun);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['success'] ? 0 : 1;
        }

        $this->newLine();

        if (!$result['success']) {
            $this->error("Rollback failed: " . ($result['reason'] ?? 'unknown'));

            foreach ($result['validation']['checks'] ?? [] as $check) {
                if (!$check['passed']) {
                    $this->line("  ✗ {$check['check']}: {$check['detail']}");
                }
            }
            return 1;
        }

        if ($dryRun) {
            $this->info("DRY RUN — Rollback to {$targetUuid} would succeed.");
        } else {
            $this->info("Rollback initiated to {$targetUuid}.");
        }

        foreach ($result['warnings'] ?? [] as $warning) {
            $this->warn("  ⚠  {$warning}");
        }

        $this->newLine();
        $this->line('  <fg=cyan>Next steps:</>');
        foreach ($result['next_steps'] ?? [] as $step) {
            $this->line("  → {$step}");
        }

        $this->newLine();
        return 0;
    }
}
