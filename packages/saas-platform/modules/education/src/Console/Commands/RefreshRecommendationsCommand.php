<?php

namespace SaaSPlatform\Modules\Education\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Education\Contracts\Services\RecommendationRefreshServiceInterface;

/**
 * education:refresh-recommendations
 *
 * Detects and expires stale active recommendations, then regenerates fresh sets.
 * Idempotent — safe to run repeatedly or from a scheduler.
 *
 * Scheduler registration example:
 *   $schedule->command('education:refresh-recommendations')->daily();
 *   $schedule->command('education:refresh-recommendations --site=5')->dailyAt('03:00');
 */
class RefreshRecommendationsCommand extends Command
{
    protected $signature = 'education:refresh-recommendations
                            {--site=   : Scope to a specific site ID}
                            {--user=   : Scope to a specific user ID}
                            {--dry-run : Report what would change without executing}';

    protected $description = 'Expire stale recommendations and regenerate fresh sets for users.';

    public function handle(RecommendationRefreshServiceInterface $service): int
    {
        $siteId  = $this->option('site')  ? (int) $this->option('site')  : null;
        $userId  = $this->option('user')  ? (int) $this->option('user')  : null;
        $dryRun  = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY-RUN] No changes will be written.');
        }

        if ($userId !== null) {
            $result = $service->refreshForUser($userId, $siteId, $dryRun);
            $this->reportUser($userId, $siteId, $result);
        } else {
            $result = $service->refreshForSite($siteId, $dryRun);
            $this->reportSite($siteId, $result);
        }

        return Command::SUCCESS;
    }

    private function reportUser(int $userId, ?int $siteId, array $result): void
    {
        $scope = $siteId ? "site={$siteId}" : 'global';
        $this->info("User {$userId} [{$scope}]");
        $this->line("  Expired:   {$result['expired']}");
        $this->line("  Refreshed: {$result['refreshed']}");
        $this->line("  Skipped:   {$result['skipped']} (dismissed/completed — never regenerated)");
    }

    private function reportSite(?int $siteId, array $result): void
    {
        $scope = $siteId ? "site={$siteId}" : 'global';
        $this->info("Refresh complete [{$scope}]");
        $this->line("  Users processed: {$result['users']}");
        $this->line("  Expired:         {$result['expired']}");
        $this->line("  Refreshed:       {$result['refreshed']}");
        $this->line("  Skipped:         {$result['skipped']} (dismissed/completed)");
    }
}
