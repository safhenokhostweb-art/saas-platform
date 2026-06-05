<?php

namespace SaaSPlatform\Modules\Education\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Education\Contracts\Services\ContentDeficitQueueServiceInterface;
use SaaSPlatform\Modules\Education\Dto\ContentDeficitQueueItemDto;

/**
 * education:build-content-deficit-queue
 *
 * Builds a content-deficit queue from ContentReadinessService output.
 * Detects modules missing articles, walkthroughs, learning paths, or fresh content.
 * Idempotent — pure read + in-memory transform.
 *
 * Scheduler registration example:
 *   $schedule->command('education:build-content-deficit-queue')->weekly();
 *   $schedule->command('education:build-content-deficit-queue --site=5')->sundays()->at('05:00');
 */
class BuildContentDeficitQueueCommand extends Command
{
    protected $signature = 'education:build-content-deficit-queue
                            {--site=   : Scope to a specific site ID}
                            {--dry-run : Output the deficit queue without persisting}';

    protected $description = 'Build a content-deficit queue from readiness scores (articles, walkthroughs, learning paths, freshness).';

    public function handle(ContentDeficitQueueServiceInterface $service): int
    {
        $siteId = $this->option('site') ? (int) $this->option('site') : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY-RUN] Deficit queue preview only — no storage side effects.');
        }

        $queue = $service->build($siteId);

        if ($queue->isEmpty()) {
            $this->info('No content deficits found. All modules meet minimum readiness requirements.');
            return Command::SUCCESS;
        }

        $criticalCount = $queue->filter(fn(ContentDeficitQueueItemDto $i) => $i->priority === 'Critical')->count();
        $highCount     = $queue->filter(fn(ContentDeficitQueueItemDto $i) => $i->priority === 'High')->count();

        $this->info("Content deficit queue: {$queue->count()} item(s) — Critical: {$criticalCount}, High: {$highCount}");

        $rows = $queue->map(fn(ContentDeficitQueueItemDto $item) => [
            $item->moduleSlug,
            $item->deficitType,
            number_format($item->readinessScore, 2),
            $item->priority,
        ])->toArray();

        $this->table(
            ['Module', 'Deficit Type', 'Readiness Score', 'Priority'],
            $rows,
        );

        return Command::SUCCESS;
    }
}
