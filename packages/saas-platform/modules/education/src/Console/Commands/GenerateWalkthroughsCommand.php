<?php

namespace SaaSPlatform\Modules\Education\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\Modules\Education\Contracts\Services\ContentDeficitQueueServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationDraftPersistenceServiceInterface;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Exceptions\AiProviderException;
use SaaSPlatform\Modules\Education\Exceptions\InvalidGeneratedContentException;
use SaaSPlatform\Modules\Education\Generation\WalkthroughDraftGenerator;
use SaaSPlatform\Modules\Education\Models\EducationGenerationLog;

/**
 * education:generate-walkthroughs
 *
 * Filters the content-deficit queue for walkthrough deficits, calls the
 * WalkthroughDraftGenerator, and persists valid results as draft articles
 * (with content_type='walkthroughs').
 *
 * Idempotent — skips titles where a matching draft already exists.
 * Each walkthrough is persisted in its own transaction.
 *
 * Scheduler example:
 *   $schedule->command('education:generate-walkthroughs --site=5 --limit=10')->weekly();
 */
class GenerateWalkthroughsCommand extends Command
{
    protected $signature = 'education:generate-walkthroughs
                            {--site=   : Scope to a specific site ID}
                            {--module= : Scope to a specific module slug}
                            {--limit=  : Maximum number of walkthroughs to generate (default: unlimited)}
                            {--dry-run : Preview generation jobs without persisting drafts}';

    protected $description = 'Generate AI walkthrough drafts from the content-deficit queue and store as drafts.';

    public function handle(
        WalkthroughDraftGenerator                 $generator,
        ContentDeficitQueueServiceInterface       $deficitQueue,
        EducationDraftPersistenceServiceInterface $persistence,
    ): int {
        $siteId   = $this->option('site')   ? (int) $this->option('site')     : null;
        $module   = $this->option('module') ? (string) $this->option('module') : null;
        $limit    = $this->option('limit')  ? (int) $this->option('limit')    : null;
        $dryRun   = (bool) $this->option('dry-run');
        $authorId = (int) env('EDUCATION_AI_AUTHOR_ID', 1);

        if ($dryRun) {
            $this->warn('[DRY-RUN] No walkthroughs will be persisted.');
        }

        if (! $generator->isEnabled()) {
            $this->warn('AI provider is not enabled (EDUCATION_AI_PROVIDER=null). No content generated.');
            return Command::SUCCESS;
        }

        $this->info('Building content-deficit queue (walkthroughs)…');

        $queue = $deficitQueue->build($siteId)
            ->filter(fn($item) => $item->deficitType === ContentDeficitQueueServiceInterface::TYPE_WALKTHROUGHS);

        if ($module !== null) {
            $queue = $queue->filter(fn($item) => $item->moduleSlug === $module);
        }

        $queue = $queue->values();

        if ($limit !== null) {
            $queue = $queue->take($limit);
        }

        if ($queue->isEmpty()) {
            $this->info('No walkthrough deficits found in queue.');
            return Command::SUCCESS;
        }

        $this->info("{$queue->count()} walkthrough deficit(s) found. Generating…");

        $created = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($queue as $item) {
            $job = new ContentGenerationJobDto(
                moduleSlug:   $item->moduleSlug,
                contentType:  ContentDeficitQueueServiceInterface::TYPE_WALKTHROUGHS,
                priority:     $item->priority,
                siteId:       $siteId,
                sourceReason: 'content_deficit',
                metadata:     ['readiness_score' => $item->readinessScore],
            );

            try {
                $dto = $generator->generate($job);
            } catch (InvalidGeneratedContentException $e) {
                $this->warn("  [SKIP] Validation failed for \"{$item->moduleSlug}\": " . implode('; ', $e->getErrors()));
                Log::warning('education:generate-walkthroughs validation failure', [
                    'module' => $item->moduleSlug,
                    'errors' => $e->getErrors(),
                ]);
                $persistence->logFailure($job, EducationGenerationLog::STATUS_VALIDATION_FAILED, $generator->providerName());
                $failed++;
                continue;
            } catch (AiProviderException $e) {
                $this->error("  [FAIL] Provider error for \"{$item->moduleSlug}\": {$e->getMessage()}");
                Log::error('education:generate-walkthroughs provider failure', [
                    'module' => $item->moduleSlug,
                    'error'  => $e->getMessage(),
                ]);
                $persistence->logFailure($job, EducationGenerationLog::STATUS_PROVIDER_ERROR, $generator->providerName());
                $failed++;
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY-RUN] Would create walkthrough draft: \"{$dto->title}\" (confidence: {$dto->confidenceScore})");
                $created++;
                continue;
            }

            if ($persistence->draftExists($job, $dto->title)) {
                $this->line("  [SKIP] Draft already exists: \"{$dto->title}\"");
                $skipped++;
                continue;
            }

            try {
                $article = $persistence->persist($dto, $job, $authorId);
                $this->line("  [OK] Walkthrough draft #{$article->id}: \"{$dto->title}\"");
                $created++;
            } catch (\Throwable $e) {
                $this->error("  [FAIL] Could not persist \"{$dto->title}\": {$e->getMessage()}");
                Log::error('education:generate-walkthroughs persistence failure', ['error' => $e->getMessage()]);
                $failed++;
            }
        }

        $this->info("Done. Created: {$created}, Skipped: {$skipped}, Failed: {$failed}.");

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
