<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\Modules\Education\Contracts\Services\AIRecommendationScoreServiceInterface;
use SaaSPlatform\Modules\Education\Dto\UserLearningProfileDto;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationCategory;
use SaaSPlatform\Modules\Education\Models\EducationFeatureUsage;
use SaaSPlatform\Modules\Education\Models\EducationRecommendation;
use SaaSPlatform\Modules\Education\Services\Education\AIRecommendationScoreService;
use Tests\TestCase;

/**
 * EDU-14A — AIRecommendationScore Engine Tests
 *
 * Coverage targets:
 *   - Empty candidate list returns empty collection
 *   - Result contains ai_score and recommendation fields
 *   - Results are sorted descending by ai_score
 *   - High knowledge gap lifts ai_score
 *   - Recent activity lowers ai_score (user already engaged)
 *   - Inactive user (no recent activity) gets maximum activity component
 *   - Heavy usage lowers usage component (user is already proficient)
 *   - Score breakdown keys present in output
 *   - Score is bounded between 0 and 100
 *   - Recommendation with no linked content gets neutral 50.0 for gap component
 */
class AIRecommendationScoreTest extends TestCase
{
    use RefreshDatabase;

    private AIRecommendationScoreServiceInterface $service;
    private UserLearningProfileDto $profile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AIRecommendationScoreServiceInterface::class);
        $this->profile = new UserLearningProfileDto(
            userId:          10,
            siteId:          null,
            skillScore:      50.0,
            onboardingScore: 50.0,
            adoptionScore:   50.0,
            engagementScore: 50.0,
            strongestModules:[],
            weakestModules:  [],
            lastActivityAt:  null,
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edge cases
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function empty_candidate_list_returns_empty_collection(): void
    {
        $result = $this->service->score(collect(), $this->profile, collect());
        $this->assertCount(0, $result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Output shape
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function result_contains_recommendation_and_ai_score(): void
    {
        $rec = $this->makeRecommendation();

        $result = $this->service->score(collect([$rec]), $this->profile, collect());

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('recommendation', $result[0]);
        $this->assertArrayHasKey('ai_score', $result[0]);
    }

    /** @test */
    public function result_contains_score_breakdown(): void
    {
        $rec    = $this->makeRecommendation();
        $result = $this->service->score(collect([$rec]), $this->profile, collect());

        $this->assertArrayHasKey('score_breakdown', $result[0]);
        $breakdown = $result[0]['score_breakdown'];

        $this->assertArrayHasKey('gap_component',      $breakdown);
        $this->assertArrayHasKey('activity_component', $breakdown);
        $this->assertArrayHasKey('usage_component',    $breakdown);
        $this->assertArrayHasKey('progress_component', $breakdown);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Sort order
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function results_are_sorted_descending_by_ai_score(): void
    {
        $cat = EducationCategory::create(['name' => 'Test', 'slug' => 'test', 'site_id' => null]);

        $articleA = EducationArticle::create(['title' => 'A', 'slug' => 'a', 'body' => 'body', 'module_slug' => 'high_gap', 'status' => 'published', 'edu_category_id' => $cat->id]);
        $articleB = EducationArticle::create(['title' => 'B', 'slug' => 'b', 'body' => 'body', 'module_slug' => 'low_gap',  'status' => 'published', 'edu_category_id' => $cat->id]);

        $recA = EducationRecommendation::create(['user_id' => 10, 'site_id' => null, 'recommendation_type' => 'article', 'article_id' => $articleA->id, 'priority' => 50, 'reason' => 'r']);
        $recB = EducationRecommendation::create(['user_id' => 10, 'site_id' => null, 'recommendation_type' => 'article', 'article_id' => $articleB->id, 'priority' => 50, 'reason' => 'r']);

        $recA->setRelation('article', $articleA);
        $recB->setRelation('article', $articleB);

        // high_gap has gap_score=95, low_gap has gap_score=10
        $gaps = collect([
            ['module_slug' => 'high_gap', 'gap_score' => 95.0, 'priority' => 'Critical'],
            ['module_slug' => 'low_gap',  'gap_score' => 10.0, 'priority' => 'Low'],
        ]);

        $result = $this->service->score(collect([$recA, $recB]), $this->profile, $gaps);

        $this->assertGreaterThanOrEqual($result[1]['ai_score'], $result[0]['ai_score']);
        $this->assertEquals('high_gap', $result[0]['recommendation']->article->module_slug);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Knowledge gap influence
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function high_knowledge_gap_produces_higher_ai_score_than_low_gap(): void
    {
        $cat  = EducationCategory::create(['name' => 'T', 'slug' => 'tt', 'site_id' => null]);
        $artH = EducationArticle::create(['title' => 'H', 'slug' => 'h', 'body' => 'body', 'module_slug' => 'high', 'status' => 'published', 'edu_category_id' => $cat->id]);
        $artL = EducationArticle::create(['title' => 'L', 'slug' => 'l', 'body' => 'body', 'module_slug' => 'low',  'status' => 'published', 'edu_category_id' => $cat->id]);

        $recH = EducationRecommendation::create(['user_id' => 10, 'site_id' => null, 'recommendation_type' => 'article', 'article_id' => $artH->id, 'priority' => 50, 'reason' => 'r']);
        $recL = EducationRecommendation::create(['user_id' => 10, 'site_id' => null, 'recommendation_type' => 'article', 'article_id' => $artL->id, 'priority' => 50, 'reason' => 'r']);
        $recH->setRelation('article', $artH);
        $recL->setRelation('article', $artL);

        $gaps = collect([
            ['module_slug' => 'high', 'gap_score' => 90.0, 'priority' => 'Critical'],
            ['module_slug' => 'low',  'gap_score' => 5.0,  'priority' => 'Low'],
        ]);

        $result = $this->service->score(collect([$recH, $recL]), $this->profile, $gaps);

        $scoreH = collect($result)->firstWhere(fn($r) => $r['recommendation']->id === $recH->id)['ai_score'];
        $scoreL = collect($result)->firstWhere(fn($r) => $r['recommendation']->id === $recL->id)['ai_score'];

        $this->assertGreaterThan($scoreL, $scoreH);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Activity component
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function inactive_user_in_module_gets_100_activity_component(): void
    {
        $cat = EducationCategory::create(['name' => 'X', 'slug' => 'xx', 'site_id' => null]);
        $art = EducationArticle::create(['title' => 'X', 'slug' => 'xa', 'body' => 'body', 'module_slug' => 'unused_mod', 'status' => 'published', 'edu_category_id' => $cat->id]);
        $rec = EducationRecommendation::create(['user_id' => 10, 'site_id' => null, 'recommendation_type' => 'article', 'article_id' => $art->id, 'priority' => 50, 'reason' => 'r']);
        $rec->setRelation('article', $art);

        $result = $this->service->score(collect([$rec]), $this->profile, collect());

        // No feature usage for this module → activity_component = 100.0
        $this->assertEquals(100.0, $result[0]['score_breakdown']['activity_component']);
    }

    /** @test */
    public function recent_activity_lowers_activity_component(): void
    {
        // User used the module today
        EducationFeatureUsage::create([
            'user_id'      => 10,
            'site_id'      => null,
            'module_slug'  => 'active_mod',
            'feature_slug' => 'f1',
            'usage_count'  => 3,
            'first_used_at'=> now(),
            'last_used_at' => now(), // today
        ]);

        $cat = EducationCategory::create(['name' => 'AM', 'slug' => 'am', 'site_id' => null]);
        $art = EducationArticle::create(['title' => 'AM', 'slug' => 'am-a', 'body' => 'body', 'module_slug' => 'active_mod', 'status' => 'published', 'edu_category_id' => $cat->id]);
        $rec = EducationRecommendation::create(['user_id' => 10, 'site_id' => null, 'recommendation_type' => 'article', 'article_id' => $art->id, 'priority' => 50, 'reason' => 'r']);
        $rec->setRelation('article', $art);

        $result = $this->service->score(collect([$rec]), $this->profile, collect());

        // Used today → days_since ≈ 0 → activity_component ≈ 0
        $this->assertLessThan(5.0, $result[0]['score_breakdown']['activity_component']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Usage component
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function heavy_usage_produces_lower_usage_component(): void
    {
        EducationFeatureUsage::create([
            'user_id'      => 10,
            'site_id'      => null,
            'module_slug'  => 'heavy_mod',
            'feature_slug' => 'f1',
            'usage_count'  => 50, // at ceiling → normalised=1.0 → component=0
            'first_used_at'=> now(),
            'last_used_at' => now(),
        ]);

        $cat = EducationCategory::create(['name' => 'HM', 'slug' => 'hm', 'site_id' => null]);
        $art = EducationArticle::create(['title' => 'HM', 'slug' => 'hm-a', 'body' => 'body', 'module_slug' => 'heavy_mod', 'status' => 'published', 'edu_category_id' => $cat->id]);
        $rec = EducationRecommendation::create(['user_id' => 10, 'site_id' => null, 'recommendation_type' => 'article', 'article_id' => $art->id, 'priority' => 50, 'reason' => 'r']);
        $rec->setRelation('article', $art);

        $result = $this->service->score(collect([$rec]), $this->profile, collect());

        $this->assertEquals(0.0, $result[0]['score_breakdown']['usage_component']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Score bounds
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function ai_score_is_bounded_between_0_and_100(): void
    {
        $rec    = $this->makeRecommendation();
        $gaps   = collect([['module_slug' => 'none', 'gap_score' => 0.0, 'priority' => 'Low']]);
        $result = $this->service->score(collect([$rec]), $this->profile, $gaps);

        $score = $result[0]['ai_score'];
        $this->assertGreaterThanOrEqual(0.0, $score);
        $this->assertLessThanOrEqual(100.0, $score);
    }

    /** @test */
    public function recommendation_with_no_linked_content_uses_neutral_gap_component(): void
    {
        $rec = EducationRecommendation::create([
            'user_id'             => 10,
            'site_id'             => null,
            'recommendation_type' => EducationRecommendation::TYPE_USAGE_TIP,
            'priority'            => 50,
            'reason'              => 'tip',
        ]);

        $result = $this->service->score(collect([$rec]), $this->profile, collect());

        $this->assertCount(1, $result);
        // No article/walkthrough/path → gap falls back to 50.0
        $this->assertEquals(50.0, $result[0]['score_breakdown']['gap_component']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function makeRecommendation(): EducationRecommendation
    {
        return EducationRecommendation::create([
            'user_id'             => 10,
            'site_id'             => null,
            'recommendation_type' => EducationRecommendation::TYPE_USAGE_TIP,
            'priority'            => 50,
            'reason'              => 'generic tip',
        ]);
    }
}
