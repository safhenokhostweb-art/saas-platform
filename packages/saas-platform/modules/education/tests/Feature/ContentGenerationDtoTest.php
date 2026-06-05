<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Orchestra\Testbench\TestCase;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;

/**
 * EDU-15 Phase 2 — DTO contracts
 */
class ContentGenerationDtoTest extends TestCase
{
    // ----- ContentGenerationJobDto -----

    public function test_job_dto_holds_all_required_fields(): void
    {
        $dto = new ContentGenerationJobDto(
            moduleSlug:   'payments',
            contentType:  'articles',
            priority:     'Critical',
            siteId:       5,
            sourceReason: 'content_deficit',
            metadata:     ['readiness_score' => 20.5],
        );

        $this->assertSame('payments', $dto->moduleSlug);
        $this->assertSame('articles', $dto->contentType);
        $this->assertSame('Critical', $dto->priority);
        $this->assertSame(5, $dto->siteId);
        $this->assertSame('content_deficit', $dto->sourceReason);
        $this->assertSame(['readiness_score' => 20.5], $dto->metadata);
    }

    public function test_job_dto_site_id_can_be_null(): void
    {
        $dto = new ContentGenerationJobDto(
            moduleSlug: 'core', contentType: 'articles', priority: 'Low',
            siteId: null, sourceReason: 'content_deficit',
        );
        $this->assertNull($dto->siteId);
    }

    public function test_job_dto_metadata_defaults_to_empty_array(): void
    {
        $dto = new ContentGenerationJobDto('core', 'articles', 'Low', null, 'content_deficit');
        $this->assertSame([], $dto->metadata);
    }

    public function test_job_dto_to_array(): void
    {
        $dto = new ContentGenerationJobDto(
            moduleSlug: 'reports', contentType: 'walkthroughs', priority: 'High',
            siteId: 2, sourceReason: 'knowledge_gap', metadata: ['gap_score' => 75.0],
        );

        $arr = $dto->toArray();
        $this->assertArrayHasKey('module_slug', $arr);
        $this->assertArrayHasKey('content_type', $arr);
        $this->assertArrayHasKey('priority', $arr);
        $this->assertArrayHasKey('site_id', $arr);
        $this->assertArrayHasKey('source_reason', $arr);
        $this->assertArrayHasKey('metadata', $arr);
        $this->assertSame('reports', $arr['module_slug']);
    }

    // ----- GeneratedContentDto -----

    public function test_generated_dto_holds_all_fields(): void
    {
        $dto = new GeneratedContentDto(
            title:           'فاکتور را بشناسید',
            summary:         'این مقاله',
            body:            str_repeat('محتوا ', 20),
            tags:            ['invoices', 'basics'],
            confidenceScore: 87.5,
            provider:        'claude',
        );

        $this->assertSame('فاکتور را بشناسید', $dto->title);
        $this->assertSame(87.5, $dto->confidenceScore);
        $this->assertSame('claude', $dto->provider);
        $this->assertSame(['invoices', 'basics'], $dto->tags);
    }

    public function test_generated_dto_to_array_has_all_keys(): void
    {
        $dto = new GeneratedContentDto('T', 'S', 'B', [], 50.0, 'null');
        $arr = $dto->toArray();

        foreach (['title', 'summary', 'body', 'tags', 'confidence_score', 'provider'] as $key) {
            $this->assertArrayHasKey($key, $arr);
        }
    }

    public function test_generated_dto_confidence_score_preserved_as_float(): void
    {
        $dto = new GeneratedContentDto('T', 'S', 'B', [], 0.0, 'null');
        $this->assertIsFloat($dto->confidenceScore);

        $dto2 = new GeneratedContentDto('T', 'S', 'B', [], 100.0, 'null');
        $this->assertIsFloat($dto2->confidenceScore);
    }
}
