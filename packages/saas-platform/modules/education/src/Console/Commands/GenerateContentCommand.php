<?php

namespace SaaSPlatform\Modules\Education\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationAiContentGenerationServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationDraftPersistenceServiceInterface;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;

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
                            {--dry-run  : Preview generation jobs without persisting drafts}';

    protected $description = 'Generate AI education content from the content-deficit queue and store as drafts.';

    public function handle(
        EducationAiContentGenerationServiceInterface $generator,
        EducationDraftPersistenceServiceInterface    $persistence,
    ): int {
        $siteId    = $this->option('site')   ? (int) $this->option('site')     : null;
        $module    = $this->option('module') ? (string) $this->option('module') : null;
        $dryRun    = (bool) $this->option('dry-run');
        $authorId  = (int) env('EDUCATION_AI_AUTHOR_ID', 1);

        if ($dryRun) {
            $this->warn('[DRY-RUN] No articles will be persisted.');
        }

        if (! $generator->isProviderEnabled()) {
            $this->warn('AI provider is not enabled (EDUCATION_AI_PROVIDER=null). No content generated.');
            return Command::SUCCESS;
        }

        $this->info('Building content-deficit queue…');
        $results = $generator->generateFromDeficits($siteId, $module);

        if ($results->isEmpty()) {
            $this->info('No content generated — queue empty or all items filtered.');
            return Command::SUCCESS;
        }

        $this->info("{$results->count()} item(s) generated. Processing…");

        $created  = 0;
        $skipped  = 0;
        $failed   = 0;

        foreach ($results as $dto) {
            /** @var GeneratedContentDto $dto */
            if ($dryRun) {
                $this->line("  [DRY-RUN] Would create draft: \"{$dto->title}\" (confidence: {$dto->confidenceScore})");
                $created++;
                continue;
            }

            // We reconstruct the job for idempotency check — the generator does not return the job,
            // so we check by title + module implicitly via draftExists.
            // Build a minimal job DTO from the result's metadata for the idempotency guard.
            // Real idempotency is enforced per title+module+site in draftExists().
            $jobProxy = new \SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto(
                moduleSlug:   $module ?? 'unknown',
                contentType:  'articles',
                priority:     'Low',
                siteId:       $siteId,
                sourceReason: 'content_deficit',
            );

            if ($persistence->draftExists($jobProxy, $dto->title)) {
                $this->line("  [SKIP] Draft already exists: \"{$dto->title}\"");
                $skipped++;
                continue;
            }

            try {
                $article = $persistence->persist($dto, $jobProxy, $authorId);
                $this->line("  [OK] Draft #{$article->id}: \"{$dto->title}\"");
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
