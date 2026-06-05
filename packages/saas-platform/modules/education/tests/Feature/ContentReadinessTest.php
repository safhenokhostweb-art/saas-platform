<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\Modules\Education\Contracts\Services\ContentReadinessServiceInterface;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationCategory;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Models\EducationLearningPath;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;
use Tests\TestCase;

/**
 * EDU-14A.1 Remediation — ContentReadiness Tests
 *
 * F-01: readiness_score must be 0–100 (not 0–1)
 * F-02: evaluate() must use O(1) query groups regardless of module count
 * PA-08: evaluateModule() must not re-query article count inside collectMissing
 */
class ContentReadinessTest extends TestCase
{
    use RefreshDatabase;

    private ContentReadinessServiceInterface $service;
    private int $catId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ContentReadinessServiceInterface::class);
        $cat = EducationCategory::create(['name' => 'Test', 'slug' => 'test-cat', 'site_id' => null]);
        $this->catId = $cat->id;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // F-01 — readiness_score range 0–100
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function readiness_score_is_0_for_module_with_no_content(): void
    {
        // Feature exists but no articles, walkthroughs, or paths
        EducationFeature::create(['module_slug' => 'empty_mod', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);

        $result = $this->service->evaluateModule('empty_mod', null);

        // F-01: must be exactly 0, not 0.0 scaled to 1.0
        $this->assertEquals(0.0, $result['readiness_score']);
        $this->assertGreaterThanOrEqual(0.0, $result['readiness_score']);
        $this->assertLessThanOrEqual(100.0, $result['readiness_score']);
    }

    /** @test */
    public function readiness_score_is_100_for_fully_covered_fresh_module(): void
    {
        // 2+ articles, 1 walkthrough, 1 learning path, fresh content
        $this->seedFullModule('full_mod', null);

        $result = $this->service->evaluateModule('full_mod', null);

        // F-01: perfect coverage = 100.0, NOT 1.0
        $this->assertEquals(100.0, $result['readiness_score']);
    }

    /** @test */
    public function readiness_score_is_approximately_50_for_half_covered_module(): void
    {
        // articles(40%) = 1 article → articleScore=0.5 → 0.5*40=20
        // walkthrough(30%) = present → 1.0*30=30
        // path(20%) = absent → 0.0*20=0
        // freshness(10%) = absent → 0.0*10=0
        // total = 50.0
        EducationFeature::create(['module_slug' => 'half_mod', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);
        EducationArticle::create([
            'title' => 'A1', 'slug' => 'a1', 'body' => 'body',
            'module_slug' => 'half_mod', 'status' => 'published',
            'edu_category_id' => $this->catId,
        ]);
        EducationWalkthrough::create([
            'title' => 'W1', 'slug' => 'w1', 'is_active' => true,
            'module_slug' => 'half_mod', 'site_id' => null,
        ]);

        $result = $this->service->evaluateModule('half_mod', null);

        // F-01: must be 50.0 (not 0.5)
        $this->assertEquals(50.0, $result['readiness_score']);
    }

    /** @test */
    public function readiness_score_is_never_outside_0_to_100_range(): void
    {
        EducationFeature::create(['module_slug' => 'range_mod', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);
        $this->seedFullModule('range_mod', null);

        $results = $this->service->evaluate(null);

        foreach ($results as $result) {
            $this->assertGreaterThanOrEqual(0.0, $result['readiness_score']);
            $this->assertLessThanOrEqual(100.0, $result['readiness_score']);
        }
    }

    /** @test */
    public function weight_components_sum_to_100_producing_correct_partial_scores(): void
    {
        // Only articles with 2 articles: articleScore=1.0*40=40; rest=0
        EducationFeature::create(['module_slug' => 'art_only', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);
        EducationArticle::create(['title' => 'B1', 'slug' => 'b1', 'body' => 'body', 'module_slug' => 'art_only', 'status' => 'published', 'edu_category_id' => $this->catId]);
        EducationArticle::create(['title' => 'B2', 'slug' => 'b2', 'body' => 'body', 'module_slug' => 'art_only', 'status' => 'published', 'edu_category_id' => $this->catId]);

        $result = $this->service->evaluateModule('art_only', null);

        // articleScore=1.0 → 40, no walkthrough=0, no path=0, no freshness=0 → 40.0
        $this->assertEquals(40.0, $result['readiness_score']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // F-02 — batch queries: O(1) groups not O(n*4)
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function evaluate_uses_constant_query_count_regardless_of_module_count(): void
    {
        // Create 4 modules with varying content
        foreach (['mod_a', 'mod_b', 'mod_c', 'mod_d'] as $slug) {
            EducationFeature::create(['module_slug' => $slug, 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);
        }
        // Add some articles to make it realistic
        EducationArticle::create(['title' => 'MA', 'slug' => 'ma', 'body' => 'body', 'module_slug' => 'mod_a', 'status' => 'published', 'edu_category_id' => $this->catId]);
        EducationArticle::create(['title' => 'MB', 'slug' => 'mb', 'body' => 'body', 'module_slug' => 'mod_b', 'status' => 'published', 'edu_category_id' => $this->catId]);

        DB::enableQueryLog();
        $this->service->evaluate(null);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // F-02: 5 queries max (1 distinct + 4 batch) regardless of 4 modules
        // Before fix: 1 + 4*4 = 17 queries
        $this->assertCount(5, $queries,
            "Expected 5 queries (1 distinct + 4 batch), got " . count($queries) . "."
        );
    }

    /** @test */
    public function evaluate_with_zero_modules_returns_empty_collection_and_runs_one_query(): void
    {
        DB::enableQueryLog();
        $results = $this->service->evaluate(null);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $results);
        // Only the distinct pluck query runs; batch queries are skipped
        $this->assertCount(1, $queries);
    }

    /** @test */
    public function evaluate_with_single_module_still_uses_batch_approach(): void
    {
        EducationFeature::create(['module_slug' => 'solo_mod', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);

        DB::enableQueryLog();
        $this->service->evaluate(null);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // 1 module: still 5 queries (1 distinct + 4 batch)
        $this->assertCount(5, $queries);
    }

    /** @test */
    public function evaluate_results_match_evaluatemodule_results_per_module(): void
    {
        EducationFeature::create(['module_slug' => 'cross_mod', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);
        EducationArticle::create(['title' => 'CM1', 'slug' => 'cm1', 'body' => 'body', 'module_slug' => 'cross_mod', 'status' => 'published', 'edu_category_id' => $this->catId]);

        $batchResult  = $this->service->evaluate(null)->firstWhere('module_slug', 'cross_mod');
        $singleResult = $this->service->evaluateModule('cross_mod', null);

        $this->assertEquals($singleResult['readiness_score'], $batchResult['readiness_score']);
        $this->assertEquals($singleResult['missing_content'], $batchResult['missing_content']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PA-08 — evaluateModule() uses exactly 4 queries (no redundant article count)
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function evaluate_module_runs_exactly_4_queries(): void
    {
        EducationFeature::create(['module_slug' => 'pa08_mod', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);

        DB::enableQueryLog();
        $this->service->evaluateModule('pa08_mod', null);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // 4 queries: articleCount, walkthroughCount, pathCount, lastGenerated
        // Before PA-08 fix: 5 queries (extra article count inside collectMissing)
        $this->assertCount(4, $queries,
            "Expected 4 queries for evaluateModule, got " . count($queries) . " (PA-08 regression)."
        );
    }

    /** @test */
    public function evaluate_module_with_partial_articles_reports_correct_needed_count(): void
    {
        EducationFeature::create(['module_slug' => 'need_one', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);
        EducationArticle::create(['title' => 'N1', 'slug' => 'n1', 'body' => 'body', 'module_slug' => 'need_one', 'status' => 'published', 'edu_category_id' => $this->catId]);

        $result = $this->service->evaluateModule('need_one', null);

        $this->assertContains(
            'نیاز به 1 مقاله منتشرشده برای ماژول need_one',
            $result['missing_content']
        );
    }

    /** @test */
    public function evaluate_module_with_zero_articles_reports_need_for_2(): void
    {
        EducationFeature::create(['module_slug' => 'zero_art', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);

        $result = $this->service->evaluateModule('zero_art', null);

        $this->assertContains(
            'نیاز به 2 مقاله منتشرشده برای ماژول zero_art',
            $result['missing_content']
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Bonus: evaluate() returns sorted results
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function evaluate_returns_results_sorted_descending_by_readiness_score(): void
    {
        // low_ready: no content
        EducationFeature::create(['module_slug' => 'low_ready', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);
        // high_ready: full content
        EducationFeature::create(['module_slug' => 'high_ready', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);
        $this->seedFullModule('high_ready', null);

        $results = $this->service->evaluate(null);

        $this->assertEquals('high_ready', $results->first()['module_slug']);
        $this->assertGreaterThanOrEqual(
            $results->last()['readiness_score'],
            $results->first()['readiness_score']
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────────

    private function seedFullModule(string $moduleSlug, ?int $siteId): void
    {
        EducationArticle::create([
            'title' => "{$moduleSlug}_A1", 'slug' => "{$moduleSlug}-a1", 'body' => 'body',
            'module_slug' => $moduleSlug, 'status' => 'published',
            'edu_category_id' => $this->catId, 'site_id' => $siteId,
            'last_generated_at' => now()->subDays(5),
        ]);
        EducationArticle::create([
            'title' => "{$moduleSlug}_A2", 'slug' => "{$moduleSlug}-a2", 'body' => 'body',
            'module_slug' => $moduleSlug, 'status' => 'published',
            'edu_category_id' => $this->catId, 'site_id' => $siteId,
            'last_generated_at' => now()->subDays(3),
        ]);
        EducationWalkthrough::create([
            'title' => "{$moduleSlug}_W", 'slug' => "{$moduleSlug}-w",
            'is_active' => true, 'module_slug' => $moduleSlug, 'site_id' => $siteId,
        ]);
        EducationLearningPath::create([
            'title' => "{$moduleSlug}_LP", 'slug' => "{$moduleSlug}-lp",
            'is_active' => true, 'module_slug' => $moduleSlug, 'site_id' => $siteId,
            'path_type' => 'manual',
        ]);
    }
}
