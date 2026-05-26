<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Deployment;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseLockManager;
use SaaSPlatform\CoreUtils\Services\Deployment\ReleaseManifestManager;
use SaaSPlatform\CoreUtils\Services\Deployment\DeploymentTransactionLogger;

class DeploymentStatusCommand extends Command
{
    protected $signature = 'deployment:status
                            {--json    : Output as JSON}
                            {--history : Show recent deployment history}';

    protected $description = 'Show current deployment status and release information';

    public function handle(
        ReleaseLockManager       $lockManager,
        ReleaseManifestManager   $manifestManager,
        DeploymentTransactionLogger $logger
    ): int {
        $activeLock     = $lockManager->getActiveLock();
        $latestManifest = $manifestManager->latest();

        if ($this->option('json')) {
            $this->line(json_encode([
                'active_lock'     => $activeLock ? (array) $activeLock : null,
                'latest_release'  => $latestManifest,
                'deployment_count'=> count($manifestManager->all()),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return 0;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>DEPLOYMENT STATUS</>');
        $this->line('  ─────────────────');
        $this->newLine();

        // Lock status
        if ($activeLock) {
            $this->line("  <fg=yellow;options=bold>⚠  DEPLOYMENT IN PROGRESS</>");
            $this->line("     UUID:    {$activeLock->deployment_uuid}");
            $this->line("     Owner:   {$activeLock->owner}");
            $this->line("     Phase:   {$activeLock->phase}");
            $this->line("     Since:   {$activeLock->acquired_at}");
            $this->line("     Expires: {$activeLock->expires_at}");
        } else {
            $this->line("  <fg=green>✓  No active deployment lock</>");
        }

        $this->newLine();

        // Latest release
        if ($latestManifest) {
            $this->line("  <fg=cyan>Latest Release</>");
            $this->line("     UUID:        {$latestManifest['deployment_uuid']}");
            $this->line("     Released at: {$latestManifest['released_at']}");
            $this->line("     Git commit:  " . ($latestManifest['git_commit'] ?? 'unknown'));
            $this->line("     Git branch:  " . ($latestManifest['git_branch'] ?? 'unknown'));
            $this->line("     Migration:   batch #{$latestManifest['migration_batch']}");
            $this->line("     Platform:    {$latestManifest['platform_version']}");
            $this->line("     Deployed by: {$latestManifest['deployed_by']}");
        } else {
            $this->line("  No release manifests found.");
        }

        if ($this->option('history')) {
            $this->newLine();
            $this->line("  <fg=cyan>Recent Deployments</>");
            foreach (array_slice($manifestManager->all(), 0, 5) as $uuid => $m) {
                $branch = $m['git_branch'] ?? '?';
                $this->line("     {$m['released_at']}  {$uuid}  ({$branch})");
            }
        }

        $this->newLine();
        return 0;
    }
}
