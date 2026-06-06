<?php

namespace SaaSPlatform\Modules\Education\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationAiContentGenerationServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationDraftPersistenceServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\KnowledgeGapQueueServiceInterface;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Exceptions\AiProviderException;
use SaaSPlatform\Modules\Education\Exceptions\InvalidGeneratedContentException;
use SaaSPlatform\Modules\Education\Models\EducationGenerationLog;

/**
 * education:generate-gap-content
 *
 * Consumes the knowledge-gap queue (Critical + High gaps only), calls the
 * configured AI provider to produce remediation content, and persists valid
 * results as draft articles.
 *
 * Idempotent — skips drafts that already exist for the same site + module + title.
 * Each article is persisted in its own transaction.
 *
 * Scheduler example:
 *   $schedule->command('education:generate-gap-content --site=5')->weekly();
 */
class GenerateGapContentCommand extends Command
{
    protected $signature = 'education:generate-gap-content
                            {--site=    : Scope to a specific site ID}
                            {--module=  : Scope to a specific module slug}
                            {--limit=   : Maximum number of items to generate (default: unlimited)}
                            {--dry-run  : Preview generation jobs without persisting drafts}';

    protected $description = 'Generate AI remediation content from the knowledge-gap queue and store as drafts.';

    public function handle(
        EducationAiContentGenerationServiceInterface $generator,
        KnowledgeGapQueueServiceInterface            $gapQueue,
        EducationDraftPersistenceServiceInterface    $persistence,
    ): int {
        $siteId   = $this->option('site')   ? (int) $this->option('site')     : null;
        $module   = $this->option('module') ? (string) $this->option('module') : null;
        $limit    = $this->option('limit')  ? (int) $this->option('limit')    : null;
        $dryRun   = (bool) $this->option('dry-run');
        $authorId = (int) env('EDUCATION_AI_AUTHOR_ID', 1);

        if ($dryRun) {
            $this->warn('[DRY-RUN] No articles will be persisted.');
        }

        if (! $generator->isProviderEnabled()) {
            $this->warn('AI provider is not enabled (EDUCATION_AI_PROVIDER=null). No content generated.');
            return Command::SUCCESS;
        }

        $this->info('Building knowledge-gap queue…');

        // Consume the queue directly to retain module slug and siteId per item.
        $queue = $gapQueue->buildForSite($siteId);

        if ($module !== null) {
            $queue = $queue->filter(fn($item) => $item->moduleSlug === $module)->values();
        }

        // Deduplicate by module+site — one remediation article per module per run.
        $queue = $queue->unique(fn($item) => $item->moduleSlug . '.' . ($item->siteId ?? 'null'))->values();

        if ($limit !== null) {
            $queue = $queue->take($limit);
        }

        if ($queue->isEmpty()) {
            $this->info('No gap remediation content generated — queue empty or all items filtered.');
            return Command::SUCCESS;
        }

        $this->info("{$queue->count()} gap item(s) found. Generating…");

        $created = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($queue as $item) {
            // Use item->siteId (not $siteId from option) so per-user gap site scope is respected.
            $job = new ContentGenerationJobDto(
                moduleSlug:   $item->moduleSlug,
                contentType:  'remediation',
                priority:     $item->priority,
                siteId:       $item->siteId,
                sourceReason: 'knowledge_gap',
                metadata:     ['gap_score' => $item->gapScore, 'user_id' => $item->userId],
            );

            try {
                $dto = $generator->executeJob($job);
            } catch (InvalidGeneratedContentException $e) {
                $this->warn("  [SKIP] Validation failed for \"{$item->moduleSlug}\": " . implode('; ', $e->getErrors()));
                Log::warning('education:generate-gap-content validation failure', [
                    'module' => $item->moduleSlug,
                    'errors' => $e->getErrors(),
                ]);
                $persistence->logFailure($job, EducationGenerationLog::STATUS_VALIDATION_FAILED, $generator->providerName());
                $failed++;
                continue;
            } catch (AiProviderException $e) {
                $this->error("  [FAIL] Provider error for \"{$item->moduleSlug}\": {$e->getMessage()}");
                Log::error('education:generate-gap-content provider failure', [
                    'module' => $item->moduleSlug,
                    'error'  => $e->getMessage(),
                ]);
                $persistence->logFailure($job, EducationGenerationLog::STATUS_PROVIDER_ERROR, $generator->providerName());
                $failed++;
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY-RUN] Would create gap-remediation draft: \"{$dto->title}\" [{$item->moduleSlug}] (confidence: {$dto->confidenceScore})");
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
                $this->line("  [OK] Gap-remediation draft #{$article->id}: \"{$dto->title}\" [{$item->moduleSlug}]");
                $created++;
            } catch (\Throwable $e) {
                $this->error("  [FAIL] Could not persist \"{$dto->title}\": {$e->getMessage()}");
                Log::error('education:generate-gap-content persistence failure', ['error' => $e->getMessage()]);
                $failed++;
            }
        }

        $this->info("Done. Created: {$created}, Skipped: {$skipped}, Failed: {$failed}.");

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
