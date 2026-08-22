<?php

namespace SaaSPlatform\Modules\Education\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\Modules\Education\Contracts\Services\ContentDeficitQueueServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationAiContentGenerationServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationDraftPersistenceServiceInterface;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Exceptions\AiProviderException;
use SaaSPlatform\Modules\Education\Exceptions\InvalidGeneratedContentException;
use SaaSPlatform\Modules\Education\Models\EducationGenerationLog;

/**
 * education:generate-content
 *
 * Consumes the content-deficit queue, calls the configured AI provider,
 * and persists valid results as draft articles.
 *
 * Idempotent — skips articles where a matching draft already exists.
 * Each article is persisted in its own transaction; one failure does not
 * abort the rest of the batch.
 *
 * The system user (id=1) is used as the author for auto-generated drafts.
 * Override via EDUCATION_AI_AUTHOR_ID env if needed.
 *
 * Scheduler example:
 *   $schedule->command('education:generate-content --site=5')->weekly();
 */
class GenerateContentCommand extends Command
{
    protected $signature = 'education:generate-content
                            {--site=    : Scope to a specific site ID}
                            {--module=  : Scope to a specific module slug}
                            {--limit=   : Maximum number of items to generate (default: unlimited)}
                            {--dry-run  : Preview generation jobs without persisting drafts}';

    protected $description = 'Generate AI education content from the content-deficit queue and store as drafts.';

    public function handle(
        EducationAiContentGenerationServiceInterface $generator,
        ContentDeficitQueueServiceInterface          $deficitQueue,
        EducationDraftPersistenceServiceInterface    $persistence,
    ): int {
        $siteId   = $this->option('site')   ? (int) $this->option('site')     : null;
        $module   = $this->option('module') ? (string) $this->option('module') : null;
        $limit    = $this->option('limit')  ? (int) $this->option('limit')    : null;
        $dryRun   = (bool) $this->option('dry-run');
        $authorId = (int) config('education.ai.author_id', 1);

        if ($dryRun) {
            $this->warn('[DRY-RUN] No articles will be persisted.');
        }

        if (! $generator->isProviderEnabled()) {
            $this->warn('AI provider is not enabled (EDUCATION_AI_PROVIDER=null). No content generated.');
            return Command::SUCCESS;
        }

        $this->info('Building content-deficit queue…');

        // Consume the queue directly so we retain the module slug per item.
        $queue = $deficitQueue->build($siteId);

        if ($module !== null) {
            $queue = $queue->filter(fn($item) => $item->moduleSlug === $module)->values();
        }

        if ($limit !== null) {
            $queue = $queue->take($limit);
        }

        if ($queue->isEmpty()) {
            $this->info('No content generated — queue empty or all items filtered.');
            return Command::SUCCESS;
        }

        $this->info("{$queue->count()} deficit item(s) found. Generating…");

        $created = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($queue as $item) {
            // Build the job per queue item — module slug is preserved correctly.
            $job = new ContentGenerationJobDto(
                moduleSlug:   $item->moduleSlug,
                contentType:  $item->deficitType,
                priority:     $item->priority,
                siteId:       $siteId,
                sourceReason: 'content_deficit',
                metadata:     ['readiness_score' => $item->readinessScore],
            );

            try {
                $dto = $generator->executeJob($job);
            } catch (InvalidGeneratedContentException $e) {
                $this->warn("  [SKIP] Validation failed for \"{$item->moduleSlug}\": " . implode('; ', $e->getErrors()));
                Log::warning('education:generate-content validation failure', [
                    'module' => $item->moduleSlug,
                    'errors' => $e->getErrors(),
                ]);
                $persistence->logFailure($job, EducationGenerationLog::STATUS_VALIDATION_FAILED, $generator->providerName());
                $failed++;
                continue;
            } catch (AiProviderException $e) {
                $this->error("  [FAIL] Provider error for \"{$item->moduleSlug}\": {$e->getMessage()}");
                Log::error('education:generate-content provider failure', [
                    'module' => $item->moduleSlug,
                    'error'  => $e->getMessage(),
                ]);
                $persistence->logFailure($job, EducationGenerationLog::STATUS_PROVIDER_ERROR, $generator->providerName());
                $failed++;
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY-RUN] Would create draft: \"{$dto->title}\" [{$item->moduleSlug}] (confidence: {$dto->confidenceScore})");
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
                $this->line("  [OK] Draft #{$article->id}: \"{$dto->title}\" [{$item->moduleSlug}]");
                $created++;
            } catch (\Throwable $e) {
                $this->error("  [FAIL] Could not persist \"{$dto->title}\": {$e->getMessage()}");
                Log::error('education:generate-content persistence failure', ['error' => $e->getMessage()]);
                $failed++;
            }
        }

        $this->info("Done. Created: {$created}, Skipped: {$skipped}, Failed: {$failed}.");

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
