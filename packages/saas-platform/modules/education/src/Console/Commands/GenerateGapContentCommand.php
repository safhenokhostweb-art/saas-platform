<?php

namespace SaaSPlatform\Modules\Education\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationAiContentGenerationServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationDraftPersistenceServiceInterface;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;

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
                            {--dry-run  : Preview generation jobs without persisting drafts}';

    protected $description = 'Generate AI remediation content from the knowledge-gap queue and store as drafts.';

    public function handle(
        EducationAiContentGenerationServiceInterface $generator,
        EducationDraftPersistenceServiceInterface    $persistence,
    ): int {
        $siteId   = $this->option('site')   ? (int) $this->option('site')     : null;
        $module   = $this->option('module') ? (string) $this->option('module') : null;
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
        $results = $generator->generateFromGaps($siteId, $module);

        if ($results->isEmpty()) {
            $this->info('No gap remediation content generated — queue empty or all items filtered.');
            return Command::SUCCESS;
        }

        $this->info("{$results->count()} item(s) generated. Processing…");

        $created = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($results as $dto) {
            /** @var GeneratedContentDto $dto */
            $jobProxy = new ContentGenerationJobDto(
                moduleSlug:   $module ?? 'unknown',
                contentType:  'remediation',
                priority:     'Critical',
                siteId:       $siteId,
                sourceReason: 'knowledge_gap',
            );

            if ($dryRun) {
                $this->line("  [DRY-RUN] Would create gap-remediation draft: \"{$dto->title}\" (confidence: {$dto->confidenceScore})");
                $created++;
                continue;
            }

            if ($persistence->draftExists($jobProxy, $dto->title)) {
                $this->line("  [SKIP] Draft already exists: \"{$dto->title}\"");
                $skipped++;
                continue;
            }

            try {
                $article = $persistence->persist($dto, $jobProxy, $authorId);
                $this->line("  [OK] Gap-remediation draft #{$article->id}: \"{$dto->title}\"");
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
