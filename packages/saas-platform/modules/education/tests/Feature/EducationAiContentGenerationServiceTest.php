<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use SaaSPlatform\Modules\Education\Ai\Providers\NullAiContentProvider;
use SaaSPlatform\Modules\Education\Contracts\Ai\AiContentProviderInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\ContentDeficitQueueServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\KnowledgeGapQueueServiceInterface;
use SaaSPlatform\Modules\Education\Dto\ContentDeficitQueueItemDto;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;
use SaaSPlatform\Modules\Education\Dto\KnowledgeGapQueueItemDto;
use SaaSPlatform\Modules\Education\Exceptions\InvalidGeneratedContentException;
use SaaSPlatform\Modules\Education\Services\Education\EducationAiContentGenerationService;
use SaaSPlatform\Modules\Education\Services\Education\GeneratedContentValidator;

/**
 * EDU-15 Phase 3 — Generation Service
 */
class EducationAiContentGenerationServiceTest extends TestCase
{
    // ----- Null provider -----

    public function test_null_provider_returns_empty_collection_from_deficits(): void
    {
        $service = $this->makeService(new NullAiContentProvider());
        $results = $service->generateFromDeficits(1);
        $this->assertTrue($results->isEmpty());
    }

    public function test_null_provider_returns_empty_collection_from_gaps(): void
    {
        $service = $this->makeService(new NullAiContentProvider());
        $results = $service->generateFromGaps(1);
        $this->assertTrue($results->isEmpty());
    }

    public function test_is_provider_enabled_reflects_provider(): void
    {
        $service = $this->makeService(new NullAiContentProvider());
        $this->assertFalse($service->isProviderEnabled());
    }

    // ----- Provider swapping -----

    public function test_swapping_to_enabled_provider_calls_generate(): void
    {
        $fakeDto = $this->validGeneratedDto();

        $provider = $this->createMock(AiContentProviderInterface::class);
        $provider->method('isEnabled')->willReturn(true);
        $provider->method('providerName')->willReturn('mock');
        $provider->method('generate')->willReturn($fakeDto);

        $deficitQueue = $this->createMock(ContentDeficitQueueServiceInterface::class);
        $deficitQueue->method('build')->willReturn(collect([
            new ContentDeficitQueueItemDto('invoices', 'articles', 20.0, 'Critical'),
        ]));

        $gapQueue = $this->createMock(KnowledgeGapQueueServiceInterface::class);
        $gapQueue->method('buildForSite')->willReturn(collect());

        $service = new EducationAiContentGenerationService(
            $provider, $deficitQueue, $gapQueue, new GeneratedContentValidator(),
        );

        $results = $service->generateFromDeficits(1);
        $this->assertCount(1, $results);
    }

    // ----- Module filtering -----

    public function test_deficit_generation_filters_by_module(): void
    {
        $fakeDto  = $this->validGeneratedDto();
        $provider = $this->mockEnabledProvider($fakeDto);

        $deficitQueue = $this->createMock(ContentDeficitQueueServiceInterface::class);
        $deficitQueue->method('build')->willReturn(collect([
            new ContentDeficitQueueItemDto('invoices', 'articles', 20.0, 'Critical'),
            new ContentDeficitQueueItemDto('reports',  'articles', 30.0, 'High'),
        ]));

        $service = new EducationAiContentGenerationService(
            $provider,
            $deficitQueue,
            $this->createMock(KnowledgeGapQueueServiceInterface::class),
            new GeneratedContentValidator(),
        );

        $results = $service->generateFromDeficits(siteId: 1, moduleSlug: 'invoices');
        $this->assertCount(1, $results);
    }

    public function test_gap_generation_filters_by_module(): void
    {
        $fakeDto  = $this->validGeneratedDto();
        $provider = $this->mockEnabledProvider($fakeDto);

        $now = \Carbon\Carbon::now();
        $gapQueue = $this->createMock(KnowledgeGapQueueServiceInterface::class);
        $gapQueue->method('buildForSite')->willReturn(collect([
            new KnowledgeGapQueueItemDto(1, 1, 'invoices', 90.0, 'Critical', $now),
            new KnowledgeGapQueueItemDto(1, 1, 'reports',  80.0, 'High',     $now),
        ]));

        $service = new EducationAiContentGenerationService(
            $provider,
            $this->createMock(ContentDeficitQueueServiceInterface::class),
            $gapQueue,
            new GeneratedContentValidator(),
        );

        $results = $service->generateFromGaps(siteId: 1, moduleSlug: 'invoices');
        $this->assertCount(1, $results);
    }

    // ----- Deduplication -----

    public function test_gap_generation_deduplicates_by_module(): void
    {
        $fakeDto  = $this->validGeneratedDto();
        $provider = $this->mockEnabledProvider($fakeDto);

        $now = \Carbon\Carbon::now();
        $gapQueue = $this->createMock(KnowledgeGapQueueServiceInterface::class);
        $gapQueue->method('buildForSite')->willReturn(collect([
            new KnowledgeGapQueueItemDto(1, 1, 'invoices', 90.0, 'Critical', $now),
            new KnowledgeGapQueueItemDto(2, 1, 'invoices', 85.0, 'Critical', $now), // same module
        ]));

        $service = new EducationAiContentGenerationService(
            $provider,
            $this->createMock(ContentDeficitQueueServiceInterface::class),
            $gapQueue,
            new GeneratedContentValidator(),
        );

        $results = $service->generateFromGaps(siteId: 1);
        $this->assertCount(1, $results); // deduplicated
    }

    // ----- Validation rejection -----

    public function test_invalid_content_is_silently_rejected(): void
    {
        $invalidDto = new GeneratedContentDto('', '', '', [], 0.0, 'mock');

        $provider = $this->mockEnabledProvider($invalidDto);

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

        $results = $service->generateFromDeficits(1);
        $this->assertTrue($results->isEmpty()); // rejected, not thrown
    }

    // ----- executeJob direct -----

    public function test_execute_job_passes_valid_result(): void
    {
        $fakeDto  = $this->validGeneratedDto();
        $provider = $this->mockEnabledProvider($fakeDto);

        $service = new EducationAiContentGenerationService(
            $provider,
            $this->createMock(ContentDeficitQueueServiceInterface::class),
            $this->createMock(KnowledgeGapQueueServiceInterface::class),
            new GeneratedContentValidator(),
        );

        $job = new ContentGenerationJobDto('invoices', 'articles', 'High', 1, 'content_deficit');
        $result = $service->executeJob($job);
        $this->assertSame($fakeDto->title, $result->title);
    }

    public function test_execute_job_throws_on_invalid_content(): void
    {
        $invalidDto = new GeneratedContentDto('', '', '', [], -1.0, 'mock');
        $provider   = $this->mockEnabledProvider($invalidDto);

        $service = new EducationAiContentGenerationService(
            $provider,
            $this->createMock(ContentDeficitQueueServiceInterface::class),
            $this->createMock(KnowledgeGapQueueServiceInterface::class),
            new GeneratedContentValidator(),
        );

        $this->expectException(InvalidGeneratedContentException::class);
        $service->executeJob(new ContentGenerationJobDto('x', 'articles', 'Low', null, 'test'));
    }

    // -----------------------------------------------------------------------

    private function makeService(AiContentProviderInterface $provider): EducationAiContentGenerationService
    {
        $deficitQueue = $this->createMock(ContentDeficitQueueServiceInterface::class);
        $deficitQueue->method('build')->willReturn(collect());

        $gapQueue = $this->createMock(KnowledgeGapQueueServiceInterface::class);
        $gapQueue->method('buildForSite')->willReturn(collect());

        return new EducationAiContentGenerationService(
            $provider, $deficitQueue, $gapQueue, new GeneratedContentValidator(),
        );
    }

    private function mockEnabledProvider(GeneratedContentDto $returns): AiContentProviderInterface
    {
        $provider = $this->createMock(AiContentProviderInterface::class);
        $provider->method('isEnabled')->willReturn(true);
        $provider->method('providerName')->willReturn('mock');
        $provider->method('generate')->willReturn($returns);
        return $provider;
    }

    private function validGeneratedDto(): GeneratedContentDto
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
