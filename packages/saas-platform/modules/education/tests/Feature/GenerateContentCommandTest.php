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
use SaaSPlatform\Modules\Education\Dto\ContentDeficitQueueItemDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Providers\EducationServiceProvider;
use SaaSPlatform\Modules\Education\Services\Education\EducationAiContentGenerationService;
use SaaSPlatform\Modules\Education\Services\Education\GeneratedContentValidator;

/**
 * EDU-15 — education:generate-content command
 *
 * After F-01 fix: command consumes queue directly and calls executeJob() per item,
 * so tests must reflect that — no longer mock generateFromDeficits().
 */
class GenerateContentCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [EducationServiceProvider::class];
    }

    // ----- Null provider -----

    public function test_command_succeeds_silently_when_provider_disabled(): void
    {
        $this->bindNullProvider();

        $this->artisan('education:generate-content')
            ->expectsOutputToContain('AI provider is not enabled')
            ->assertSuccessful();
    }

    // ----- Dry-run -----

    public function test_dry_run_does_not_create_articles(): void
    {
        $this->bindFakeSetup($this->validDto());

        $this->artisan('education:generate-content', ['--dry-run' => true])
            ->expectsOutputToContain('DRY-RUN')
            ->assertSuccessful();

        $this->assertDatabaseCount('edu_articles', 0);
    }

    public function test_dry_run_outputs_would_create_message(): void
    {
        $this->bindFakeSetup($this->validDto());

        $this->artisan('education:generate-content', ['--dry-run' => true])
            ->expectsOutputToContain('Would create draft');
    }

    // ----- Module scoping: queue is filtered before executeJob -----

    public function test_module_option_filters_queue_items(): void
    {
        // Two items in queue; --module=invoices should process only 1
        $queue = collect([
            new ContentDeficitQueueItemDto('invoices', 'articles', 20.0, 'Critical'),
            new ContentDeficitQueueItemDto('reports',  'articles', 30.0, 'High'),
        ]);

        $deficitQueue = $this->createMock(ContentDeficitQueueServiceInterface::class);
        $deficitQueue->method('build')->willReturn($queue);

        $generator = $this->createMock(EducationAiContentGenerationServiceInterface::class);
        $generator->method('isProviderEnabled')->willReturn(true);
        $generator->expects($this->once())  // only 1 job after filter
            ->method('executeJob')
            ->willReturn($this->validDto());

        $this->app->bind(ContentDeficitQueueServiceInterface::class, fn() => $deficitQueue);
        $this->app->bind(EducationAiContentGenerationServiceInterface::class, fn() => $generator);
        $this->app->bind(EducationDraftPersistenceServiceInterface::class, function () {
            $m = $this->createMock(EducationDraftPersistenceServiceInterface::class);
            $m->method('draftExists')->willReturn(false);
            $m->method('persist')->willReturnCallback(fn() => $this->makeArticle());
            return $m;
        });

        $this->artisan('education:generate-content', ['--module' => 'invoices'])
            ->assertSuccessful();
    }

    // ----- module_slug is NOT 'unknown' in persisted article (regression guard for F-01) -----

    public function test_persisted_article_has_correct_module_slug_when_no_module_option(): void
    {
        $capturedJob = null;

        $queue = collect([
            new ContentDeficitQueueItemDto('payments', 'articles', 15.0, 'Critical'),
        ]);

        $deficitQueue = $this->createMock(ContentDeficitQueueServiceInterface::class);
        $deficitQueue->method('build')->willReturn($queue);

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

        $this->app->bind(ContentDeficitQueueServiceInterface::class, fn() => $deficitQueue);
        $this->app->bind(EducationAiContentGenerationServiceInterface::class, fn() => $generator);
        $this->app->bind(EducationDraftPersistenceServiceInterface::class, fn() => $persistence);

        $this->artisan('education:generate-content')->assertSuccessful();

        // The critical assertion: module_slug must be 'payments', NOT 'unknown'
        $this->assertNotNull($capturedJob);
        $this->assertSame('payments', $capturedJob->moduleSlug);
        $this->assertNotSame('unknown', $capturedJob->moduleSlug);
    }

    // ----- Site scoping -----

    public function test_site_option_scopes_deficit_queue_build(): void
    {
        $deficitQueue = $this->createMock(ContentDeficitQueueServiceInterface::class);
        $deficitQueue->expects($this->once())
            ->method('build')
            ->with(5)
            ->willReturn(collect());

        $generator = $this->createMock(EducationAiContentGenerationServiceInterface::class);
        $generator->method('isProviderEnabled')->willReturn(true);

        $this->app->bind(ContentDeficitQueueServiceInterface::class, fn() => $deficitQueue);
        $this->app->bind(EducationAiContentGenerationServiceInterface::class, fn() => $generator);
        $this->app->bind(EducationDraftPersistenceServiceInterface::class, fn() => $this->createMock(EducationDraftPersistenceServiceInterface::class));

        $this->artisan('education:generate-content', ['--site' => '5'])
            ->assertSuccessful();
    }

    // ----- Idempotency -----

    public function test_skips_existing_draft(): void
    {
        $this->bindFakeSetup($this->validDto(), draftExists: true);

        $this->artisan('education:generate-content')
            ->expectsOutputToContain('SKIP')
            ->assertSuccessful();
    }

    // ----- Limit option -----

    public function test_limit_option_caps_processing(): void
    {
        $queue = collect([
            new ContentDeficitQueueItemDto('mod1', 'articles', 10.0, 'Critical'),
            new ContentDeficitQueueItemDto('mod2', 'articles', 20.0, 'High'),
            new ContentDeficitQueueItemDto('mod3', 'articles', 30.0, 'Medium'),
        ]);

        $deficitQueue = $this->createMock(ContentDeficitQueueServiceInterface::class);
        $deficitQueue->method('build')->willReturn($queue);

        $generator = $this->createMock(EducationAiContentGenerationServiceInterface::class);
        $generator->method('isProviderEnabled')->willReturn(true);
        $generator->expects($this->exactly(2))  // limit=2
            ->method('executeJob')
            ->willReturn($this->validDto());

        $persistence = $this->createMock(EducationDraftPersistenceServiceInterface::class);
        $persistence->method('draftExists')->willReturn(false);
        $persistence->method('persist')->willReturnCallback(fn() => $this->makeArticle());

        $this->app->bind(ContentDeficitQueueServiceInterface::class, fn() => $deficitQueue);
        $this->app->bind(EducationAiContentGenerationServiceInterface::class, fn() => $generator);
        $this->app->bind(EducationDraftPersistenceServiceInterface::class, fn() => $persistence);

        $this->artisan('education:generate-content', ['--limit' => '2'])->assertSuccessful();
    }

    // ----- Transaction safety -----

    public function test_one_failure_does_not_abort_batch(): void
    {
        $queue = collect([
            new ContentDeficitQueueItemDto('mod1', 'articles', 10.0, 'Critical'),
            new ContentDeficitQueueItemDto('mod2', 'articles', 20.0, 'High'),
        ]);

        $deficitQueue = $this->createMock(ContentDeficitQueueServiceInterface::class);
        $deficitQueue->method('build')->willReturn($queue);

        $generator = $this->createMock(EducationAiContentGenerationServiceInterface::class);
        $generator->method('isProviderEnabled')->willReturn(true);
        $generator->method('executeJob')->willReturn($this->validDto());

        $callCount = 0;
        $persistence = $this->createMock(EducationDraftPersistenceServiceInterface::class);
        $persistence->method('draftExists')->willReturn(false);
        $persistence->method('persist')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) throw new \RuntimeException('DB error');
            return $this->makeArticle();
        });

        $this->app->bind(ContentDeficitQueueServiceInterface::class, fn() => $deficitQueue);
        $this->app->bind(EducationAiContentGenerationServiceInterface::class, fn() => $generator);
        $this->app->bind(EducationDraftPersistenceServiceInterface::class, fn() => $persistence);

        $this->artisan('education:generate-content')
            ->expectsOutputToContain('FAIL')
            ->expectsOutputToContain('OK');
    }

    // -----------------------------------------------------------------------

    private function bindNullProvider(): void
    {
        $this->app->bind(AiContentProviderInterface::class, NullAiContentProvider::class);
    }

    private function bindFakeSetup(GeneratedContentDto $dto, bool $draftExists = false): void
    {
        $queue = collect([
            new ContentDeficitQueueItemDto('invoices', 'articles', 20.0, 'Critical'),
        ]);

        $deficitQueue = $this->createMock(ContentDeficitQueueServiceInterface::class);
        $deficitQueue->method('build')->willReturn($queue);

        $generator = $this->createMock(EducationAiContentGenerationServiceInterface::class);
        $generator->method('isProviderEnabled')->willReturn(true);
        $generator->method('executeJob')->willReturn($dto);

        $persistence = $this->createMock(EducationDraftPersistenceServiceInterface::class);
        $persistence->method('draftExists')->willReturn($draftExists);
        $persistence->method('persist')->willReturnCallback(fn() => $this->makeArticle());

        $this->app->bind(ContentDeficitQueueServiceInterface::class, fn() => $deficitQueue);
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
            title:           'عنوان مقاله آموزشی',
            summary:         'خلاصه',
            body:            str_repeat('محتوا ', 15),
            tags:            [],
            confidenceScore: 80.0,
            provider:        'mock',
        );
    }
}
