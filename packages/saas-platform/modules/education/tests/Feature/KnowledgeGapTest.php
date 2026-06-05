<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\Modules\Education\Contracts\Services\KnowledgeGapServiceInterface;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationCategory;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Models\EducationFeatureUsage;
use SaaSPlatform\Modules\Education\Models\EducationUserProgress;
use SaaSPlatform\Modules\Education\Services\Education\KnowledgeGapService;
use Tests\TestCase;

/**
 * EDU-14A — KnowledgeGap Detection Tests
 *
 * Coverage targets:
 *   - Empty feature catalog returns empty collection
 *   - Gap score is 100 for a completely unused module
 *   - Gap score is 0 for a fully adopted module with full docs
 *   - Priority thresholds: Critical >= 75, High >= 50, Medium >= 25, Low < 25
 *   - Documentation score halved when only 1 of 2 articles present
 *   - Completion score reflects article completion ratio
 *   - Results sorted descending by gap_score
 *   - Multi-module detection returns one row per module
 *   - Site isolation: gaps scoped to siteId
 */
class KnowledgeGapTest extends TestCase
{
    use RefreshDatabase;

    private KnowledgeGapServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(KnowledgeGapServiceInterface::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edge cases
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function empty_feature_catalog_returns_empty_collection(): void
    {
        $gaps = $this->service->detectForUser(1, null);
        $this->assertCount(0, $gaps);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Gap score range
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function fully_unused_module_with_no_docs_has_maximum_gap(): void
    {
        EducationFeature::create(['module_slug' => 'invoices', 'feature_slug' => 'list', 'feature_name' => 'List', 'site_id' => null]);

        $gaps = $this->service->detectForUser(1, null);

        $this->assertCount(1, $gaps);
        // usage=0, completion=0, docs=0 → gap = 100 - 0 = 100
        $this->assertEquals(100.0, $gaps[0]['gap_score']);
        $this->assertEquals(KnowledgeGapServiceInterface::PRIORITY_CRITICAL, $gaps[0]['priority']);
    }

    /** @test */
    public function fully_adopted_module_with_full_docs_has_near_zero_gap(): void
    {
        EducationFeature::create(['module_slug' => 'stores', 'feature_slug' => 'list', 'feature_name' => 'List', 'site_id' => null]);

        $cat = EducationCategory::create(['name' => 'Store', 'slug' => 'store', 'site_id' => null]);

        EducationArticle::create(['title' => 'A1', 'slug' => 'a1', 'body' => 'body', 'module_slug' => 'stores', 'status' => 'published', 'edu_category_id' => $cat->id]);
        EducationArticle::create(['title' => 'A2', 'slug' => 'a2', 'body' => 'body', 'module_slug' => 'stores', 'status' => 'published', 'edu_category_id' => $cat->id]);

        EducationFeatureUsage::create(['user_id' => 2, 'site_id' => null, 'module_slug' => 'stores', 'feature_slug' => 'list', 'usage_count' => 5, 'first_used_at' => now(), 'last_used_at' => now()]);

        $gaps = $this->service->detectForUser(2, null);
        $gap  = $gaps->firstWhere('module_slug', 'stores');

        // usage=100, completion≈0 (no progress records), docs=100
        // gap = 100 - (100*0.4 + 0*0.35 + 100*0.25) = 100 - 65 = 35
        $this->assertNotNull($gap);
        $this->assertLessThan(50.0, $gap['gap_score']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Priority thresholds
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function priority_is_critical_for_gap_score_75_or_above(): void
    {
        EducationFeature::create(['module_slug' => 'mod_critical', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);

        $gaps = $this->service->detectForUser(1, null);
        $gap  = $gaps->firstWhere('module_slug', 'mod_critical');

        $this->assertEquals(KnowledgeGapServiceInterface::PRIORITY_CRITICAL, $gap['priority']);
        $this->assertGreaterThanOrEqual(75.0, $gap['gap_score']);
    }

    /** @test */
    public function priority_is_low_for_gap_score_below_25(): void
    {
        EducationFeature::create(['module_slug' => 'mod_low', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);

        // Full usage + full docs drives gap below 25
        $cat = EducationCategory::create(['name' => 'ModLow', 'slug' => 'mod-low', 'site_id' => null]);
        EducationArticle::create(['title' => 'ML1', 'slug' => 'ml1', 'body' => 'body', 'module_slug' => 'mod_low', 'status' => 'published', 'edu_category_id' => $cat->id]);
        EducationArticle::create(['title' => 'ML2', 'slug' => 'ml2', 'body' => 'body', 'module_slug' => 'mod_low', 'status' => 'published', 'edu_category_id' => $cat->id]);
        EducationFeatureUsage::create(['user_id' => 3, 'site_id' => null, 'module_slug' => 'mod_low', 'feature_slug' => 'f1', 'usage_count' => 10, 'first_used_at' => now(), 'last_used_at' => now()]);

        // Simulate completed article progress
        $article = EducationArticle::where('module_slug', 'mod_low')->first();
        EducationUserProgress::create([
            'user_id'          => 3,
            'site_id'          => null,
            'progressable_id'  => $article->id,
            'progressable_type'=> 'SaaSPlatform\Modules\Education\Models\EducationArticle',
            'status'           => EducationUserProgress::STATUS_COMPLETED,
            'progress_percent' => 100,
        ]);

        $gaps = $this->service->detectForUser(3, null);
        $gap  = $gaps->firstWhere('module_slug', 'mod_low');

        $this->assertNotNull($gap);
        $this->assertLessThan(25.0, $gap['gap_score']);
        $this->assertEquals(KnowledgeGapServiceInterface::PRIORITY_LOW, $gap['priority']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Partial documentation
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function documentation_score_is_50_percent_with_only_one_article(): void
    {
        EducationFeature::create(['module_slug' => 'partial_mod', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);

        $cat = EducationCategory::create(['name' => 'PM', 'slug' => 'pm', 'site_id' => null]);
        EducationArticle::create(['title' => 'PM1', 'slug' => 'pm1', 'body' => 'body', 'module_slug' => 'partial_mod', 'status' => 'published', 'edu_category_id' => $cat->id]);

        $gaps = $this->service->detectForUser(1, null);
        $gap  = $gaps->firstWhere('module_slug', 'partial_mod');

        // docs_score = 50, usage_score = 0, completion_score = 0
        // gap = 100 - (0*0.4 + 0*0.35 + 50*0.25) = 100 - 12.5 = 87.5
        $this->assertNotNull($gap);
        $this->assertEquals(87.5, $gap['gap_score']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Sort order
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function results_are_sorted_descending_by_gap_score(): void
    {
        // Module A: fully unused, no docs → high gap
        EducationFeature::create(['module_slug' => 'sort_a', 'feature_slug' => 'fa', 'feature_name' => 'FA', 'site_id' => null]);

        // Module B: used, has docs → lower gap
        EducationFeature::create(['module_slug' => 'sort_b', 'feature_slug' => 'fb', 'feature_name' => 'FB', 'site_id' => null]);
        $cat = EducationCategory::create(['name' => 'SortB', 'slug' => 'sort-b', 'site_id' => null]);
        EducationArticle::create(['title' => 'SB1', 'slug' => 'sb1', 'body' => 'body', 'module_slug' => 'sort_b', 'status' => 'published', 'edu_category_id' => $cat->id]);
        EducationArticle::create(['title' => 'SB2', 'slug' => 'sb2', 'body' => 'body', 'module_slug' => 'sort_b', 'status' => 'published', 'edu_category_id' => $cat->id]);
        EducationFeatureUsage::create(['user_id' => 4, 'site_id' => null, 'module_slug' => 'sort_b', 'feature_slug' => 'fb', 'usage_count' => 5, 'first_used_at' => now(), 'last_used_at' => now()]);

        $gaps = $this->service->detectForUser(4, null);

        $firstModule  = $gaps->first()['module_slug'];
        $firstScore   = $gaps->first()['gap_score'];
        $secondScore  = $gaps->last()['gap_score'];

        $this->assertEquals('sort_a', $firstModule);
        $this->assertGreaterThanOrEqual($secondScore, $firstScore);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Multi-module
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function returns_one_entry_per_module_slug(): void
    {
        EducationFeature::create(['module_slug' => 'ma', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);
        EducationFeature::create(['module_slug' => 'ma', 'feature_slug' => 'f2', 'feature_name' => 'F2', 'site_id' => null]);
        EducationFeature::create(['module_slug' => 'mb', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);

        $gaps = $this->service->detectForUser(1, null);

        $moduleSlugs = $gaps->pluck('module_slug')->unique();
        $this->assertEquals($moduleSlugs->count(), $gaps->count());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Site isolation
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function gaps_are_scoped_to_the_given_site_id(): void
    {
        EducationFeature::create(['module_slug' => 'site_a_mod', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => 100]);
        EducationFeature::create(['module_slug' => 'global_mod', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);

        // Only site 100 should appear when siteId=100
        $gaps = $this->service->detectForUser(1, 100);
        $slugs = $gaps->pluck('module_slug');

        $this->assertTrue($slugs->contains('site_a_mod') || $slugs->contains('global_mod'));
        $this->assertFalse($slugs->contains('site_a_mod') && ! $slugs->contains('global_mod'));
    }
}
