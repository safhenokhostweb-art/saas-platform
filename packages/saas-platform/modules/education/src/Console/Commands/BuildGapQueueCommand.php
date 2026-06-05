<?php

namespace SaaSPlatform\Modules\Education\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Education\Contracts\Services\KnowledgeGapQueueServiceInterface;
use SaaSPlatform\Modules\Education\Dto\KnowledgeGapQueueItemDto;

/**
 * education:build-gap-queue
 *
 * Builds a knowledge-gap remediation queue from KnowledgeGapService output.
 * Only Critical and High priority gaps are surfaced.
 * Idempotent — pure read + in-memory transform, no side effects.
 *
 * Scheduler registration example:
 *   $schedule->command('education:build-gap-queue')->weekly();
 *   $schedule->command('education:build-gap-queue --site=5')->dailyAt('04:00');
 */
class BuildGapQueueCommand extends Command
{
    protected $signature = 'education:build-gap-queue
                            {--site=   : Scope to a specific site ID}
                            {--user=   : Scope to a specific user ID}
                            {--dry-run : Output the queue without persisting}';

    protected $description = 'Build a knowledge-gap remediation queue (Critical + High gaps only).';

    public function handle(KnowledgeGapQueueServiceInterface $service): int
    {
        $siteId = $this->option('site') ? (int) $this->option('site') : null;
        $userId = $this->option('user') ? (int) $this->option('user') : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY-RUN] Queue preview only — no storage side effects.');
        }

        $queue = $userId !== null
            ? $service->buildForUser($userId, $siteId)
            : $service->buildForSite($siteId);

        if ($queue->isEmpty()) {
            $this->info('No Critical or High gaps found. Queue is empty.');
            return Command::SUCCESS;
        }

        $this->info("Gap queue built: {$queue->count()} item(s)");

        $rows = $queue->map(fn(KnowledgeGapQueueItemDto $item) => [
            $item->userId,
            $item->siteId,
            $item->moduleSlug,
            number_format($item->gapScore, 2),
            $item->priority,
        ])->toArray();

        $this->table(
            ['User', 'Site', 'Module', 'Gap Score', 'Priority'],
            $rows,
        );

        return Command::SUCCESS;
    }
}
