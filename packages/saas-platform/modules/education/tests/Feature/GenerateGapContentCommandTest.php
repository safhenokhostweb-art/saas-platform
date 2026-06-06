<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use SaaSPlatform\Modules\Education\Ai\Providers\NullAiContentProvider;
use SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\ContentDeficitQueueServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationAiContentGenerationServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationDraftPersistenceServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\KnowledgeGapQueueServiceInterface;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Dto\KnowledgeGapQueueItemDto;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Providers\EducationServiceProvider;

/**
 * EDU-15 — education:generate-gap-content command
 *
 * After F-01 fix: command consumes gap queue directly and calls executeJob()
 * per item, preserving the real module slug and site_id from each queue item.
 */
class GenerateGapContentCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [EducationServiceProvider::class];
    }

    // ----- Null provider -----

    public function test_command_exits_silently_when_provider_disabled(): void
    {
        $this->app->bind(AiContentProviderInterface::class, NullAiContentProvider::class);

        $this->artisan('education:generate-gap-content')
            ->expectsOutputToContain('AI provider is not enabled')
            ->assertSuccessful();
    }

    // ----- Dry-run -----

    public function test_dry_run_does_not_create_articles(): void
    {
        $this->bindFakeSetup($this->validDto());

        $this->artisan('education:generate-gap-content', ['--dry-run' => true])
            ->expectsOutputToContain('DRY-RUN')
            ->assertSuccessful();

        $this->assertDatabaseCount('edu_articles', 0);
    }

    public function test_dry_run_outputs_would_create_message(): void
    {
        $this->bindFakeSetup($this->validDto());

        $this->artisan('education:generate-gap-content', ['--dry-run' => true])
            ->expectsOutputToContain('Would create gap-remediation draft');
    }

    // ----- module_slug is NOT 'unknown' (F-01 regression guard) -----

    public function test_persisted_article_has_correct_module_slug_from_gap_item(): void
    {
        $capturedJob = null;
        $now = \Carbon\Carbon::now();

        $gapQueue = $this->createMock(KnowledgeGapQueueServiceInterface::class);
        $gapQueue->method('buildForSite')->willReturn(collect([
            new KnowledgeGapQueueItemDto(1, 1, 'payments', 90.0, 'Critical', $now),
        ]));

        $generator = $this->createMock(EducationAiContentGenerationServiceInterface::class);
        $generator->method('isProviderEnabled')->willReturn(true);
        $generator->method('executeJob')->willReturn($this->validDto());

        $persistence = $this->createMock(EducationDraftPersistenceServiceInterface::class);
        $persistence->method('draftExists')->willReturn(false);
        $persistence->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($dto, $job) use (&$capturedJob) {
                $capturedJob = $job;
                return $this->makeArticle();
            });

        $this->app->bind(KnowledgeGapQueueServiceInterface::class, fn() => $gapQueue);
        $this->app->bind(EducationAiContentGenerationServiceInterface::class, fn() => $generator);
        $this->app->bind(EducationDraftPersistenceServiceInterface::class, fn() => $persistence);

        $this->artisan('education:generate-gap-content')->assertSuccessful();

        // Critical: module_slug must be 'payments', NOT 'unknown'
        $this->assertNotNull($capturedJob);
        $this->assertSame('payments', $capturedJob->moduleSlug);
        $this->assertNotSame('unknown', $capturedJob->moduleSlug);
    }

    // ----- site_id comes from the gap item, not just the --site option -----

    public function test_persisted_article_site_id_comes_from_gap_item(): void
    {
        $capturedJob = null;
        $now = \Carbon\Carbon::now();

        $gapQueue = $this->createMock(KnowledgeGapQueueServiceInterface::class);
        // Gap item has siteId=7
        $gapQueue->method('buildForSite')->willReturn(collect([
            new KnowledgeGapQueueItemDto(1, 7, 'invoices', 90.0, 'Critical', $now),
        ]));

        $generator = $this->createMock(EducationAiContentGenerationServiceInterface::class);
        $generator->method('isProviderEnabled')->willReturn(true);
        $generator->method('executeJob')->willReturn($this->validDto());

        $persistence = $this->createMock(EducationDraftPersistenceServiceInterface::class);
        $persistence->method('draftExists')->willReturn(false);
        $persistence->method('persist')->willReturnCallback(function ($dto, $job) use (&$capturedJob) {
            $capturedJob = $job;
            return $this->makeArticle();
        });

        $this->app->bind(KnowledgeGapQueueServiceInterface::class, fn() => $gapQueue);
        $this->app->bind(EducationAiContentGenerationServiceInterface::class, fn() => $generator);
        $this->app->bind(EducationDraftPersistenceServiceInterface::class, fn() => $persistence);

        $this->artisan('education:generate-gap-content')->assertSuccessful();

        $this->assertSame(7, $capturedJob->siteId);
    }

    // ----- Site option scopes gap queue -----

    public function test_site_option_passed_to_gap_queue(): void
    {
        $gapQueue = $this->createMock(KnowledgeGapQueueServiceInterface::class);
        $gapQueue->expects($this->once())
            ->method('buildForSite')
            ->with(3)
            ->willReturn(collect());

        $generator = $this->createMock(EducationAiContentGenerationServiceInterface::class);
        $generator->method('isProviderEnabled')->willReturn(true);

        $this->app->bind(KnowledgeGapQueueServiceInterface::class, fn() => $gapQueue);
        $this->app->bind(EducationAiContentGenerationServiceInterface::class, fn() => $generator);
        $this->app->bind(EducationDraftPersistenceServiceInterface::class, fn() => $this->createMock(EducationDraftPersistenceServiceInterface::class));

        $this->artisan('education:generate-gap-content', ['--site' => '3'])
            ->assertSuccessful();
    }

    // ----- Module filtering -----

    public function test_module_option_filters_gap_items(): void
    {
        $now = \Carbon\Carbon::now();
        $gapQueue = $this->createMock(KnowledgeGapQueueServiceInterface::class);
        $gapQueue->method('buildForSite')->willReturn(collect([
            new KnowledgeGapQueueItemDto(1, 1, 'invoices', 90.0, 'Critical', $now),
            new KnowledgeGapQueueItemDto(2, 1, 'reports',  80.0, 'High',     $now),
        ]));

        $generator = $this->createMock(EducationAiContentGenerationServiceInterface::class);
        $generator->method('isProviderEnabled')->willReturn(true);
        $generator->expects($this->once())  // only 'payments' after module filter... wait, filter is 'invoices'
            ->method('executeJob')
            ->willReturn($this->validDto());

        $persistence = $this->createMock(EducationDraftPersistenceServiceInterface::class);
        $persistence->method('draftExists')->willReturn(false);
        $persistence->method('persist')->willReturnCallback(fn() => $this->makeArticle());

        $this->app->bind(KnowledgeGapQueueServiceInterface::class, fn() => $gapQueue);
        $this->app->bind(EducationAiContentGenerationServiceInterface::class, fn() => $generator);
        $this->app->bind(EducationDraftPersistenceServiceInterface::class, fn() => $persistence);

        $this->artisan('education:generate-gap-content', ['--module' => 'invoices'])
            ->assertSuccessful();
    }

    // ----- Idempotency -----

    public function test_skips_existing_gap_draft(): void
    {
        $this->bindFakeSetup($this->validDto(), draftExists: true);

        $this->artisan('education:generate-gap-content')
            ->expectsOutputToContain('SKIP')
            ->assertSuccessful();
    }

    // ----- Empty queue -----

    public function test_empty_gap_queue_succeeds_silently(): void
    {
        $gapQueue = $this->createMock(KnowledgeGapQueueServiceInterface::class);
        $gapQueue->method('buildForSite')->willReturn(collect());

        $generator = $this->createMock(EducationAiContentGenerationServiceInterface::class);
        $generator->method('isProviderEnabled')->willReturn(true);

        $this->app->bind(KnowledgeGapQueueServiceInterface::class, fn() => $gapQueue);
        $this->app->bind(EducationAiContentGenerationServiceInterface::class, fn() => $generator);
        $this->app->bind(EducationDraftPersistenceServiceInterface::class, fn() => $this->createMock(EducationDraftPersistenceServiceInterface::class));

        $this->artisan('education:generate-gap-content')
            ->expectsOutputToContain('queue empty')
            ->assertSuccessful();
    }

    // ----- Failure exit code -----

    public function test_failed_persistence_returns_failure_exit_code(): void
    {
        $persistence = $this->createMock(EducationDraftPersistenceServiceInterface::class);
        $persistence->method('draftExists')->willReturn(false);
        $persistence->method('persist')->willThrowException(new \RuntimeException('DB down'));

        $this->app->bind(EducationDraftPersistenceServiceInterface::class, fn() => $persistence);
        $this->bindFakeSetup($this->validDto());

        $this->artisan('education:generate-gap-content')
            ->expectsOutputToContain('FAIL')
            ->assertFailed();
    }

    // -----------------------------------------------------------------------

    private function bindFakeSetup(GeneratedContentDto $dto, bool $draftExists = false): void
    {
        $now = \Carbon\Carbon::now();
        $gapQueue = $this->createMock(KnowledgeGapQueueServiceInterface::class);
        $gapQueue->method('buildForSite')->willReturn(collect([
            new KnowledgeGapQueueItemDto(1, 1, 'invoices', 90.0, 'Critical', $now),
        ]));

        $generator = $this->createMock(EducationAiContentGenerationServiceInterface::class);
        $generator->method('isProviderEnabled')->willReturn(true);
        $generator->method('executeJob')->willReturn($dto);

        $persistence = $this->createMock(EducationDraftPersistenceServiceInterface::class);
        $persistence->method('draftExists')->willReturn($draftExists);
        $persistence->method('persist')->willReturnCallback(fn() => $this->makeArticle());

        $this->app->bind(KnowledgeGapQueueServiceInterface::class, fn() => $gapQueue);
        $this->app->bind(EducationAiContentGenerationServiceInterface::class, fn() => $generator);
        $this->app->bind(EducationDraftPersistenceServiceInterface::class, fn() => $persistence);
    }

    private function makeArticle(): EducationArticle
    {
        $a = new EducationArticle(['title' => 'test']);
        $a->id = 1;
        return $a;
    }

    private function validDto(): GeneratedContentDto
    {
        return new GeneratedContentDto(
            title:           'درک شکاف دانشی در فاکتورها',
            summary:         'خلاصه',
            body:            str_repeat('محتوا ', 15),
            tags:            [],
            confidenceScore: 85.0,
            provider:        'mock',
        );
    }
}
