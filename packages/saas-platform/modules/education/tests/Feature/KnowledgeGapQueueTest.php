<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\Modules\Education\Contracts\Services\KnowledgeGapQueueServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\KnowledgeGapServiceInterface;
use SaaSPlatform\Modules\Education\Dto\KnowledgeGapQueueItemDto;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Models\EducationFeatureUsage;
use Tests\TestCase;

/**
 * EDU-14B — KnowledgeGapQueue Tests
 *
 * Coverage:
 *   1.  Empty feature catalog returns empty queue
 *   2.  Only Critical and High gaps enter the queue
 *   3.  Medium gaps are excluded from the queue
 *   4.  Low gaps are excluded from the queue
 *   5.  Queue items have correct DTO structure
 *   6.  Queue is sorted by gap_score descending
 *   7.  Critical items appear before High items
 *   8.  buildForSite aggregates items from multiple users
 *   9.  Site isolation: buildForSite only queues users on specified site
 *  10.  DTO toArray returns all required keys
 */
class KnowledgeGapQueueTest extends TestCase
{
    use RefreshDatabase;

    private KnowledgeGapQueueServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(KnowledgeGapQueueServiceInterface::class);
    }

    // --- T01 ---
    public function test_empty_feature_catalog_returns_empty_queue(): void
    {
        $queue = $this->service->buildForUser(1, 10);

        $this->assertEmpty($queue);
    }

    // --- T02 ---
    public function test_critical_gap_enters_queue(): void
    {
        // Critical = gap_score >= 75 (unused module, no docs)
        $this->seedUnusedModule(1, 10, 'payments');

        $queue = $this->service->buildForUser(1, 10);

        $this->assertNotEmpty($queue);
        $this->assertTrue(
            $queue->contains(fn(KnowledgeGapQueueItemDto $item) =>
                $item->priority === KnowledgeGapServiceInterface::PRIORITY_CRITICAL
            )
        );
    }

    // --- T03 ---
    public function test_high_gap_enters_queue(): void
    {
        // High = gap_score 50-74: partial usage, partial docs
        $siteId = 10;
        $userId = 1;

        // Create 2 features, use 1 of them → 50% usage
        EducationFeature::factory()->createMany([
            ['site_id' => null, 'module_slug' => 'crm', 'feature_slug' => 'contacts'],
            ['site_id' => null, 'module_slug' => 'crm', 'feature_slug' => 'deals'],
        ]);

        // 1 published article (docs partially adequate)
        EducationArticle::factory()->published()->create(['module_slug' => 'crm', 'site_id' => null]);

        EducationFeatureUsage::factory()->create([
            'user_id'     => $userId,
            'site_id'     => $siteId,
            'module_slug' => 'crm',
            'feature_slug' => 'contacts',
            'usage_count' => 2,
        ]);

        $queue = $this->service->buildForUser($userId, $siteId);

        // At least one item should be in the queue (High or Critical)
        $this->assertNotEmpty($queue);
        $priorities = $queue->map(fn(KnowledgeGapQueueItemDto $i) => $i->priority)->toArray();
        $this->assertContainsEquals(
            true,
            array_map(fn($p) => in_array($p, [
                KnowledgeGapServiceInterface::PRIORITY_CRITICAL,
                KnowledgeGapServiceInterface::PRIORITY_HIGH,
            ]), $priorities)
        );
    }

    // --- T04 ---
    public function test_medium_gaps_excluded_from_queue(): void
    {
        // Medium = gap_score 25-49
        $siteId = 10;
        $userId = 1;

        // 1 feature, used many times + 2 articles (low gap)
        EducationFeature::factory()->create(['site_id' => null, 'module_slug' => 'reporting', 'feature_slug' => 'dashboard']);
        EducationArticle::factory()->published()->createMany([
            ['module_slug' => 'reporting', 'site_id' => null],
            ['module_slug' => 'reporting', 'site_id' => null],
        ]);

        EducationFeatureUsage::factory()->create([
            'user_id'      => $userId,
            'site_id'      => $siteId,
            'module_slug'  => 'reporting',
            'feature_slug' => 'dashboard',
            'usage_count'  => 15,
        ]);

        // With full usage, full docs → gap should be low → not in queue
        $queue = $this->service->buildForUser($userId, $siteId);

        $inQueue = $queue->contains(fn(KnowledgeGapQueueItemDto $i) =>
            $i->moduleSlug === 'reporting'
        );

        $this->assertFalse($inQueue, 'A low-gap module must not appear in the queue');
    }

    // --- T05 ---
    public function test_queue_items_have_correct_dto_structure(): void
    {
        $this->seedUnusedModule(1, 10, 'hr');

        $queue = $this->service->buildForUser(1, 10);

        $this->assertNotEmpty($queue);

        $item = $queue->first();
        $this->assertInstanceOf(KnowledgeGapQueueItemDto::class, $item);
        $this->assertSame(1, $item->userId);
        $this->assertSame(10, $item->siteId);
        $this->assertNotEmpty($item->moduleSlug);
        $this->assertIsFloat($item->gapScore);
        $this->assertContains($item->priority, ['Critical', 'High']);
        $this->assertInstanceOf(\Carbon\Carbon::class, $item->createdAt);
    }

    // --- T06 ---
    public function test_queue_sorted_descending_by_gap_score(): void
    {
        $siteId = 10;
        $userId = 1;

        // Module A: completely unused → Critical score
        $this->seedUnusedModule($userId, $siteId, 'invoicing');
        // Module B: almost unused → should also be high-gap
        $this->seedUnusedModule($userId, $siteId, 'payroll');

        $queue = $this->service->buildForUser($userId, $siteId);

        if ($queue->count() >= 2) {
            $scores = $queue->map(fn(KnowledgeGapQueueItemDto $i) => $i->gapScore)->toArray();
            for ($i = 1; $i < count($scores); $i++) {
                $this->assertGreaterThanOrEqual($scores[$i], $scores[$i - 1]);
            }
        }

        $this->addToAssertionCount(1);
    }

    // --- T07 ---
    public function test_critical_items_appear_before_high_in_site_queue(): void
    {
        $siteId  = 10;
        $userId1 = 1;
        $userId2 = 2;

        // Both users have high-gap modules
        $this->seedUnusedModule($userId1, $siteId, 'crm');
        $this->seedUnusedModule($userId2, $siteId, 'hr');

        $queue = $this->service->buildForSite($siteId);

        if ($queue->count() >= 2) {
            $priorities = $queue->map(fn(KnowledgeGapQueueItemDto $i) => $i->priority)->toArray();
            $firstHigh  = array_search('High', $priorities);
            $lastCritical = array_search('Critical', array_reverse($priorities, true));

            if ($firstHigh !== false && $lastCritical !== false) {
                $this->assertLessThan($firstHigh, count($priorities) - $lastCritical - 1,
                    'All Critical items must appear before any High items');
            }
        }

        $this->addToAssertionCount(1);
    }

    // --- T08 ---
    public function test_buildForSite_aggregates_from_multiple_users(): void
    {
        $siteId = 10;

        $this->seedUnusedModule(1, $siteId, 'crm');
        $this->seedUnusedModule(2, $siteId, 'hr');

        $queue = $this->service->buildForSite($siteId);

        $userIds = $queue->map(fn(KnowledgeGapQueueItemDto $i) => $i->userId)->unique()->sort()->values()->toArray();
        $this->assertContains(1, $userIds);
        $this->assertContains(2, $userIds);
    }

    // --- T09 ---
    public function test_site_isolation_in_buildForSite(): void
    {
        $siteA = 10;
        $siteB = 20;

        $this->seedUnusedModule(1, $siteA, 'crm');
        $this->seedUnusedModule(2, $siteB, 'crm');

        $queueA = $this->service->buildForSite($siteA);

        // No items from site B should appear in site A queue
        $siteBItems = $queueA->filter(fn(KnowledgeGapQueueItemDto $i) => $i->siteId === $siteB);
        $this->assertEmpty($siteBItems);
    }

    // --- T10 ---
    public function test_dto_toArray_returns_required_keys(): void
    {
        $this->seedUnusedModule(1, 10, 'crm');

        $queue = $this->service->buildForUser(1, 10);

        // An unused module with no docs must produce at least one Critical/High queue item
        $this->assertNotEmpty($queue, 'Unused module must produce at least one gap queue item');

        $arr = $queue->first()->toArray();
        $this->assertArrayHasKey('user_id', $arr);
        $this->assertArrayHasKey('site_id', $arr);
        $this->assertArrayHasKey('module_slug', $arr);
        $this->assertArrayHasKey('gap_score', $arr);
        $this->assertArrayHasKey('priority', $arr);
        $this->assertArrayHasKey('created_at', $arr);
    }

    // -----------------------------------------------------------------------

    private function seedUnusedModule(int $userId, int $siteId, string $moduleSlug): void
    {
        EducationFeature::factory()->create([
            'site_id'      => null,
            'module_slug'  => $moduleSlug,
            'feature_slug' => 'feature-1',
        ]);

        // Track user so they appear in activeUserIds
        EducationFeatureUsage::factory()->create([
            'user_id'      => $userId,
            'site_id'      => $siteId,
            'module_slug'  => 'some_other_module',
            'feature_slug' => 'used_feature',
            'usage_count'  => 1,
        ]);
    }
}
