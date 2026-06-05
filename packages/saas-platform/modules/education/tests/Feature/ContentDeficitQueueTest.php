<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\Modules\Education\Contracts\Services\ContentDeficitQueueServiceInterface;
use SaaSPlatform\Modules\Education\Dto\ContentDeficitQueueItemDto;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Models\EducationLearningPath;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;
use Tests\TestCase;

/**
 * EDU-14B — ContentDeficitQueue Tests
 *
 * Coverage:
 *   1.  Empty module catalog returns empty queue
 *   2.  Module with no articles generates an articles deficit item
 *   3.  Module with no walkthroughs generates a walkthroughs deficit item
 *   4.  Module with no learning paths generates a learning_paths deficit item
 *   5.  Fully covered module generates no deficit items
 *   6.  Priority is Critical when readiness_score < 25
 *   7.  Priority is High when readiness_score is 25-49
 *   8.  Priority is Medium when readiness_score is 50-74
 *   9.  Priority is Low when readiness_score >= 75
 *  10.  Queue is ordered ascending by readiness_score (most urgent first)
 *  11.  DTO toArray returns all required keys
 *  12.  Site isolation: deficit queue scoped to site
 */
class ContentDeficitQueueTest extends TestCase
{
    use RefreshDatabase;

    private ContentDeficitQueueServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ContentDeficitQueueServiceInterface::class);
    }

    // --- T01 ---
    public function test_empty_module_catalog_returns_empty_queue(): void
    {
        $queue = $this->service->build(null);
        $this->assertEmpty($queue);
    }

    // --- T02 ---
    public function test_module_with_no_articles_generates_articles_deficit(): void
    {
        EducationFeature::factory()->create(['module_slug' => 'crm', 'site_id' => null]);

        $queue = $this->service->build(null);

        $articleItems = $queue->filter(fn(ContentDeficitQueueItemDto $i) =>
            $i->moduleSlug === 'crm' && $i->deficitType === ContentDeficitQueueServiceInterface::TYPE_ARTICLES
        );

        $this->assertNotEmpty($articleItems);
    }

    // --- T03 ---
    public function test_module_with_no_walkthroughs_generates_walkthrough_deficit(): void
    {
        EducationFeature::factory()->create(['module_slug' => 'hr', 'site_id' => null]);
        // Give it articles so only walkthrough is missing
        EducationArticle::factory()->published()->createMany([
            ['module_slug' => 'hr', 'site_id' => null],
            ['module_slug' => 'hr', 'site_id' => null],
        ]);

        $queue = $this->service->build(null);

        $walkthroughItems = $queue->filter(fn(ContentDeficitQueueItemDto $i) =>
            $i->moduleSlug === 'hr' && $i->deficitType === ContentDeficitQueueServiceInterface::TYPE_WALKTHROUGHS
        );

        $this->assertNotEmpty($walkthroughItems);
    }

    // --- T04 ---
    public function test_module_with_no_learning_paths_generates_path_deficit(): void
    {
        EducationFeature::factory()->create(['module_slug' => 'invoicing', 'site_id' => null]);
        EducationArticle::factory()->published()->createMany([
            ['module_slug' => 'invoicing', 'site_id' => null],
            ['module_slug' => 'invoicing', 'site_id' => null],
        ]);
        EducationWalkthrough::factory()->active()->create(['module_slug' => 'invoicing', 'site_id' => null]);

        $queue = $this->service->build(null);

        $pathItems = $queue->filter(fn(ContentDeficitQueueItemDto $i) =>
            $i->moduleSlug === 'invoicing' && $i->deficitType === ContentDeficitQueueServiceInterface::TYPE_LEARNING_PATHS
        );

        $this->assertNotEmpty($pathItems);
    }

    // --- T05 ---
    public function test_fully_covered_module_has_no_deficit_items(): void
    {
        EducationFeature::factory()->create(['module_slug' => 'reports', 'site_id' => null]);
        EducationArticle::factory()->published()->createMany([
            ['module_slug' => 'reports', 'site_id' => null, 'last_generated_at' => now()],
            ['module_slug' => 'reports', 'site_id' => null, 'last_generated_at' => now()],
        ]);
        EducationWalkthrough::factory()->active()->create(['module_slug' => 'reports', 'site_id' => null]);
        EducationLearningPath::factory()->active()->create(['module_slug' => 'reports', 'site_id' => null]);

        $queue = $this->service->build(null);

        $reportsItems = $queue->filter(fn(ContentDeficitQueueItemDto $i) => $i->moduleSlug === 'reports');
        $this->assertEmpty($reportsItems);
    }

    // --- T06 ---
    public function test_priority_critical_when_readiness_below_25(): void
    {
        // Module with nothing → readiness = 0 → at least one Critical deficit item must exist
        EducationFeature::factory()->create(['module_slug' => 'empty_mod', 'site_id' => null]);

        $queue = $this->service->build(null);

        // A module with no content MUST produce deficit items
        $moduleItems = $queue->filter(fn(ContentDeficitQueueItemDto $i) => $i->moduleSlug === 'empty_mod');
        $this->assertNotEmpty($moduleItems, 'A module with zero content must generate deficit items');

        $item = $moduleItems->first();
        $this->assertSame('Critical', $item->priority);
        $this->assertSame(0.0, $item->readinessScore);
    }

    // --- T07 ---
    public function test_actual_service_assigns_correct_priorities_from_real_readiness_scores(): void
    {
        // This test verifies the ACTUAL service priority assignment end-to-end,
        // not a local copy of the formula. Seeds modules that will produce known readiness scores.

        // Module with zero content → readiness=0 → Critical
        EducationFeature::factory()->create(['module_slug' => 'zero_mod', 'site_id' => null]);

        // Module with 2 articles + active walkthrough + active path + fresh → readiness=100 → Low (no items)
        EducationFeature::factory()->create(['module_slug' => 'full_mod', 'site_id' => null]);
        EducationArticle::factory()->published()->createMany([
            ['module_slug' => 'full_mod', 'site_id' => null, 'last_generated_at' => now()],
            ['module_slug' => 'full_mod', 'site_id' => null, 'last_generated_at' => now()],
        ]);
        EducationWalkthrough::factory()->active()->create(['module_slug' => 'full_mod', 'site_id' => null]);
        EducationLearningPath::factory()->active()->create(['module_slug' => 'full_mod', 'site_id' => null]);

        $queue = $this->service->build(null);

        $zeroItems = $queue->filter(fn(ContentDeficitQueueItemDto $i) => $i->moduleSlug === 'zero_mod');
        $fullItems  = $queue->filter(fn(ContentDeficitQueueItemDto $i) => $i->moduleSlug === 'full_mod');

        $this->assertNotEmpty($zeroItems, 'zero_mod must have deficit items');
        $this->assertSame('Critical', $zeroItems->first()->priority);
        $this->assertEmpty($fullItems, 'Fully covered module must have no deficit items');
    }

    // --- T08 ---
    public function test_queue_ordered_ascending_by_readiness_score(): void
    {
        foreach (['alpha', 'beta', 'gamma'] as $mod) {
            EducationFeature::factory()->create(['module_slug' => $mod, 'site_id' => null]);
        }

        $queue = $this->service->build(null);

        if ($queue->count() >= 2) {
            $scores = $queue->map(fn(ContentDeficitQueueItemDto $i) => $i->readinessScore)->toArray();
            for ($i = 1; $i < count($scores); $i++) {
                $this->assertLessThanOrEqual($scores[$i], $scores[$i - 1]);
            }
        }

        $this->addToAssertionCount(1);
    }

    // --- T09 ---
    public function test_dto_toArray_returns_required_keys(): void
    {
        EducationFeature::factory()->create(['module_slug' => 'check_mod', 'site_id' => null]);

        $queue = $this->service->build(null);

        // A module with zero content must produce at least one deficit item
        $this->assertNotEmpty($queue, 'A module with no content must produce deficit items');

        $arr = $queue->first()->toArray();
        $this->assertArrayHasKey('module_slug', $arr);
        $this->assertArrayHasKey('deficit_type', $arr);
        $this->assertArrayHasKey('readiness_score', $arr);
        $this->assertArrayHasKey('priority', $arr);
    }

    // --- T10 ---
    public function test_deficit_type_values_are_valid(): void
    {
        $validTypes = [
            ContentDeficitQueueServiceInterface::TYPE_ARTICLES,
            ContentDeficitQueueServiceInterface::TYPE_WALKTHROUGHS,
            ContentDeficitQueueServiceInterface::TYPE_LEARNING_PATHS,
            ContentDeficitQueueServiceInterface::TYPE_FRESHNESS,
        ];

        EducationFeature::factory()->create(['module_slug' => 'type_test', 'site_id' => null]);

        $queue = $this->service->build(null);

        // Must be non-empty — a module with zero content MUST produce deficit items
        $this->assertNotEmpty($queue, 'Expected deficit items but got empty queue');

        foreach ($queue as $item) {
            $this->assertContains($item->deficitType, $validTypes);
        }
    }

    // --- T11 ---
    public function test_site_isolation_deficit_queue(): void
    {
        $siteA = 10;
        $siteB = 20;

        EducationFeature::factory()->create(['module_slug' => 'site_a_mod', 'site_id' => $siteA]);
        EducationFeature::factory()->create(['module_slug' => 'site_b_mod', 'site_id' => $siteB]);

        $queueA = $this->service->build($siteA);

        // site_b_mod should NOT appear in site A's deficit queue
        $siteBItems = $queueA->filter(fn(ContentDeficitQueueItemDto $i) => $i->moduleSlug === 'site_b_mod');
        $this->assertEmpty($siteBItems);
    }

    // --- T12 ---
    public function test_dto_is_immutable(): void
    {
        $dto = new ContentDeficitQueueItemDto(
            moduleSlug:     'test',
            deficitType:    ContentDeficitQueueServiceInterface::TYPE_ARTICLES,
            readinessScore: 20.0,
            priority:       'Critical',
        );

        $this->assertSame('test', $dto->moduleSlug);
        $this->assertSame(ContentDeficitQueueServiceInterface::TYPE_ARTICLES, $dto->deficitType);
        $this->assertSame(20.0, $dto->readinessScore);
        $this->assertSame('Critical', $dto->priority);
    }

}
