<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\ContentDeficitQueueServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\KnowledgeGapQueueServiceInterface;
use SaaSPlatform\Modules\Education\Dto\ContentDeficitQueueItemDto;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Dto\KnowledgeGapQueueItemDto;
use SaaSPlatform\Modules\Education\Exceptions\AiProviderException;
use SaaSPlatform\Modules\Education\Models\EducationGenerationLog;
use SaaSPlatform\Modules\Education\Providers\EducationServiceProvider;
use SaaSPlatform\Modules\Education\Services\Education\EducationAiContentGenerationService;
use SaaSPlatform\Modules\Education\Services\Education\GeneratedContentValidator;

/**
 * EDU-15.2 Hardening — Aggregate path regression guard.
 *
 * Purpose:
 *   Documents the known F-02 analytics gap in executeJobs() and locks its
 *   current behaviour so any future change that routes failures through this
 *   path will cause an explicit test failure — forcing the developer to add
 *   logFailure() calls and update this suite before merging.
 *
 * Current production state (verified 2026-06-05):
 *   generateFromDeficits() and generateFromGaps() are NOT called by any
 *   persistence command. All active commands consume the queue directly and
 *   call executeJob() + logFailure() themselves. This path is unreachable in
 *   production persistence workflows.
 *
 * IF executeJobs() is later wired to a persistence workflow:
 *   See the hardening note in EducationAiContentGenerationService::executeJobs()
 *   for the exact three steps required to restore analytics accuracy.
 *   Update the assertions in this file from assertSame(0, ...) to assertSame(1, ...).
 */
class EDU152AggregatePathTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [EducationServiceProvider::class];
    }

    // =========================================================================
    // Documented gap — aggregate path does NOT write failure logs
    // These tests will FAIL intentionally if logFailure() is added to
    // executeJobs() without updating this file. That is the desired behaviour.
    // =========================================================================

    public function test_generateFromDeficits_validation_failure_writes_no_generation_log(): void
    {
        // Provider returns an invalid DTO (empty title/body) — GeneratedContentValidator
        // will throw InvalidGeneratedContentException, caught in executeJobs().
        $provider = $this->mockEnabledProvider(new GeneratedContentDto('', '', '', [], 0.0, 'claude'));

        $deficitQueue = $this->createMock(ContentDeficitQueueServiceInterface::class);
        $deficitQueue->method('build')->willReturn(collect([
            new ContentDeficitQueueItemDto('invoices', 'articles', 20.0, 'Critical'),
        ]));

        $service = new EducationAiContentGenerationService(
            $provider,
            $deficitQueue,
            $this->createMock(KnowledgeGapQueueServiceInterface::class),
            new GeneratedContentValidator(),
        );

        $results = $service->generateFromDeficits(siteId: 1);

        // The exception is swallowed — result is empty.
        $this->assertTrue($results->isEmpty());

        // DOCUMENTED GAP: no EducationGenerationLog row is written.
        // When executeJobs() gains persistence, change this to assertSame(1, ...).
        $this->assertSame(0, EducationGenerationLog::count(),
            'Aggregate path does not write failure logs. ' .
            'If this assertion fails, executeJobs() now writes logs — update this test and verify F-02 invariant.'
        );
    }

    public function test_generateFromDeficits_provider_error_writes_no_generation_log(): void
    {
        $provider = $this->createMock(AiContentProviderInterface::class);
        $provider->method('isEnabled')->willReturn(true);
        $provider->method('providerName')->willReturn('claude');
        $provider->method('generate')->willThrowException(new AiProviderException('network error'));

        $deficitQueue = $this->createMock(ContentDeficitQueueServiceInterface::class);
        $deficitQueue->method('build')->willReturn(collect([
            new ContentDeficitQueueItemDto('payments', 'articles', 10.0, 'Critical'),
        ]));

        $service = new EducationAiContentGenerationService(
            $provider,
            $deficitQueue,
            $this->createMock(KnowledgeGapQueueServiceInterface::class),
            new GeneratedContentValidator(),
        );

        $results = $service->generateFromDeficits(siteId: 1);

        $this->assertTrue($results->isEmpty());

        // DOCUMENTED GAP: no EducationGenerationLog row is written.
        $this->assertSame(0, EducationGenerationLog::count(),
            'Aggregate path does not write failure logs. ' .
            'If this assertion fails, executeJobs() now writes logs — update this test and verify F-02 invariant.'
        );
    }

    public function test_generateFromGaps_provider_error_writes_no_generation_log(): void
    {
        $provider = $this->createMock(AiContentProviderInterface::class);
        $provider->method('isEnabled')->willReturn(true);
        $provider->method('providerName')->willReturn('openai');
        $provider->method('generate')->willThrowException(new AiProviderException('timeout'));

        $now = \Carbon\Carbon::now();
        $gapQueue = $this->createMock(KnowledgeGapQueueServiceInterface::class);
        $gapQueue->method('buildForSite')->willReturn(collect([
            new KnowledgeGapQueueItemDto(1, 1, 'invoices', 90.0, 'Critical', $now),
        ]));

        $service = new EducationAiContentGenerationService(
            $provider,
            $this->createMock(ContentDeficitQueueServiceInterface::class),
            $gapQueue,
            new GeneratedContentValidator(),
        );

        $results = $service->generateFromGaps(siteId: 1);

        $this->assertTrue($results->isEmpty());

        // DOCUMENTED GAP: no EducationGenerationLog row is written.
        $this->assertSame(0, EducationGenerationLog::count(),
            'Aggregate path does not write failure logs. ' .
            'If this assertion fails, executeJobs() now writes logs — update this test and verify F-02 invariant.'
        );
    }

    // =========================================================================
    // Success path — unchanged
    // =========================================================================

    public function test_generateFromDeficits_success_returns_dto_collection(): void
    {
        $provider = $this->mockEnabledProvider($this->validDto());

        $deficitQueue = $this->createMock(ContentDeficitQueueServiceInterface::class);
        $deficitQueue->method('build')->willReturn(collect([
            new ContentDeficitQueueItemDto('invoices', 'articles', 20.0, 'Critical'),
            new ContentDeficitQueueItemDto('reports',  'articles', 40.0, 'High'),
        ]));

        $service = new EducationAiContentGenerationService(
            $provider,
            $deficitQueue,
            $this->createMock(KnowledgeGapQueueServiceInterface::class),
            new GeneratedContentValidator(),
        );

        $results = $service->generateFromDeficits(siteId: 1);

        $this->assertCount(2, $results);
        $this->assertContainsOnlyInstancesOf(GeneratedContentDto::class, $results->all());
    }

    public function test_generateFromGaps_success_returns_dto_collection(): void
    {
        $provider = $this->mockEnabledProvider($this->validDto());

        $now = \Carbon\Carbon::now();
        $gapQueue = $this->createMock(KnowledgeGapQueueServiceInterface::class);
        $gapQueue->method('buildForSite')->willReturn(collect([
            new KnowledgeGapQueueItemDto(1, 1, 'invoices', 90.0, 'Critical', $now),
        ]));

        $service = new EducationAiContentGenerationService(
            $provider,
            $this->createMock(ContentDeficitQueueServiceInterface::class),
            $gapQueue,
            new GeneratedContentValidator(),
        );

        $results = $service->generateFromGaps(siteId: 1);

        $this->assertCount(1, $results);
    }

    public function test_executeJob_success_path_returns_dto_without_touching_db(): void
    {
        $provider = $this->mockEnabledProvider($this->validDto());

        $service = new EducationAiContentGenerationService(
            $provider,
            $this->createMock(ContentDeficitQueueServiceInterface::class),
            $this->createMock(KnowledgeGapQueueServiceInterface::class),
            new GeneratedContentValidator(),
        );

        $job = new ContentGenerationJobDto('invoices', 'articles', 'High', 1, 'content_deficit');
        $result = $service->executeJob($job);

        $this->assertInstanceOf(GeneratedContentDto::class, $result);

        // Service never touches edu_generation_logs — persistence is the command's job.
        $this->assertSame(0, EducationGenerationLog::count());
    }

    // =========================================================================
    // Partial-failure batch — success items are still returned
    // =========================================================================

    public function test_executeJobs_continues_after_one_failure(): void
    {
        $callCount = 0;
        $provider  = $this->createMock(AiContentProviderInterface::class);
        $provider->method('isEnabled')->willReturn(true);
        $provider->method('providerName')->willReturn('claude');
        $provider->method('generate')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            // First call fails, second succeeds.
            if ($callCount === 1) {
                throw new AiProviderException('first call fails');
            }
            return $this->validDto();
        });

        $deficitQueue = $this->createMock(ContentDeficitQueueServiceInterface::class);
        $deficitQueue->method('build')->willReturn(collect([
            new ContentDeficitQueueItemDto('invoices', 'articles', 10.0, 'Critical'),
            new ContentDeficitQueueItemDto('reports',  'articles', 20.0, 'High'),
        ]));

        $service = new EducationAiContentGenerationService(
            $provider,
            $deficitQueue,
            $this->createMock(KnowledgeGapQueueServiceInterface::class),
            new GeneratedContentValidator(),
        );

        $results = $service->generateFromDeficits(siteId: 1);

        // Only the second item succeeded.
        $this->assertCount(1, $results);
    }

    // =========================================================================

    private function mockEnabledProvider(GeneratedContentDto $returns): AiContentProviderInterface
    {
        $provider = $this->createMock(AiContentProviderInterface::class);
        $provider->method('isEnabled')->willReturn(true);
        $provider->method('providerName')->willReturn('mock');
        $provider->method('generate')->willReturn($returns);
        return $provider;
    }

    private function validDto(): GeneratedContentDto
    {
        return new GeneratedContentDto(
            title:           'عنوان مقاله آموزشی مناسب',
            summary:         'خلاصه مقاله',
            body:            str_repeat('محتوای آموزشی مفید ', 8),
            tags:            ['invoices'],
            confidenceScore: 80.0,
            provider:        'mock',
        );
    }
}
