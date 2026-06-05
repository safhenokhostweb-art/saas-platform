<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationAnalyticsServiceInterface;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Models\EducationFeatureUsage;
use SaaSPlatform\Modules\Education\Models\EducationRecommendation;
use SaaSPlatform\Modules\Education\Models\EducationUserProgress;
use Tests\TestCase;

/**
 * EDU-14B — EducationAnalytics Tests
 *
 * Coverage:
 *   1.  userAnalytics returns all required top-level keys
 *   2.  userAnalytics profile_scores contains all sub-keys
 *   3.  userAnalytics completion_rate is 0.0 for user with no progress
 *   4.  userAnalytics completion_rate is accurate
 *   5.  userAnalytics recommendation_acceptance_rate is 0.0 when no resolved recs
 *   6.  userAnalytics recommendation_acceptance_rate is accurate
 *   7.  siteAnalytics returns all required top-level keys
 *   8.  siteAnalytics module_adoption counts distinct users per module
 *   9.  siteAnalytics gap_distribution sums to total modules analyzed
 *  10.  siteAnalytics readiness_distribution has correct keys
 *  11.  platformAnalytics returns all required keys
 *  12.  platformAnalytics global_adoption counts unique users
 *  13.  platformAnalytics content_coverage_percent is 0 when no articles
 *  14.  platformAnalytics recommendation_effectiveness is 0 when no resolved recs
 *  15.  site isolation in userAnalytics
 */
class EducationAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private EducationAnalyticsServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EducationAnalyticsServiceInterface::class);
    }

    // --- T01 ---
    public function test_userAnalytics_returns_required_keys(): void
    {
        $result = $this->service->userAnalytics(1, null);

        $this->assertArrayHasKey('profile_scores', $result);
        $this->assertArrayHasKey('completion_rate', $result);
        $this->assertArrayHasKey('recommendation_acceptance_rate', $result);
        $this->assertArrayHasKey('recommendations', $result);
    }

    // --- T02 ---
    public function test_userAnalytics_profile_scores_has_all_sub_keys(): void
    {
        $result = $this->service->userAnalytics(1, null);

        $scores = $result['profile_scores'];
        $this->assertArrayHasKey('skill_score', $scores);
        $this->assertArrayHasKey('onboarding_score', $scores);
        $this->assertArrayHasKey('adoption_score', $scores);
        $this->assertArrayHasKey('engagement_score', $scores);
        $this->assertArrayHasKey('overall_score', $scores);
        $this->assertArrayHasKey('strongest_modules', $scores);
        $this->assertArrayHasKey('weakest_modules', $scores);
    }

    // --- T03 ---
    public function test_completion_rate_is_zero_for_user_with_no_progress(): void
    {
        $result = $this->service->userAnalytics(999, null);

        $this->assertSame(0.0, $result['completion_rate']);
    }

    // --- T04 ---
    public function test_completion_rate_is_accurate(): void
    {
        $userId = 1;
        $siteId = null;

        // 2 completed, 2 in-progress → rate = 0.5
        EducationArticle::factory()->published()->createMany([
            ['module_slug' => 'crm', 'site_id' => null, 'id' => null],
            ['module_slug' => 'crm', 'site_id' => null, 'id' => null],
            ['module_slug' => 'crm', 'site_id' => null, 'id' => null],
            ['module_slug' => 'crm', 'site_id' => null, 'id' => null],
        ]);

        $articles = EducationArticle::all();

        EducationUserProgress::factory()->create([
            'user_id'           => $userId,
            'site_id'           => $siteId,
            'progressable_id'   => $articles[0]->id,
            'progressable_type' => EducationArticle::class,
            'status'            => EducationUserProgress::STATUS_COMPLETED,
        ]);
        EducationUserProgress::factory()->create([
            'user_id'           => $userId,
            'site_id'           => $siteId,
            'progressable_id'   => $articles[1]->id,
            'progressable_type' => EducationArticle::class,
            'status'            => EducationUserProgress::STATUS_COMPLETED,
        ]);
        EducationUserProgress::factory()->create([
            'user_id'           => $userId,
            'site_id'           => $siteId,
            'progressable_id'   => $articles[2]->id,
            'progressable_type' => EducationArticle::class,
            'status'            => EducationUserProgress::STATUS_IN_PROGRESS,
        ]);
        EducationUserProgress::factory()->create([
            'user_id'           => $userId,
            'site_id'           => $siteId,
            'progressable_id'   => $articles[3]->id,
            'progressable_type' => EducationArticle::class,
            'status'            => EducationUserProgress::STATUS_NOT_STARTED,
        ]);

        $result = $this->service->userAnalytics($userId, $siteId);

        $this->assertEqualsWithDelta(0.5, $result['completion_rate'], 0.001);
    }

    // --- T05 ---
    public function test_recommendation_acceptance_rate_zero_when_none_resolved(): void
    {
        $result = $this->service->userAnalytics(999, null);

        $this->assertSame(0.0, $result['recommendation_acceptance_rate']);
    }

    // --- T06 ---
    public function test_recommendation_acceptance_rate_is_accurate(): void
    {
        $userId = 5;
        $siteId = 10;

        // 3 completed, 1 dismissed, 1 active → acceptance = 3/(3+1) = 0.75
        EducationRecommendation::factory()->create(['user_id' => $userId, 'site_id' => $siteId, 'completed_at' => now(), 'dismissed_at' => null]);
        EducationRecommendation::factory()->create(['user_id' => $userId, 'site_id' => $siteId, 'completed_at' => now(), 'dismissed_at' => null]);
        EducationRecommendation::factory()->create(['user_id' => $userId, 'site_id' => $siteId, 'completed_at' => now(), 'dismissed_at' => null]);
        EducationRecommendation::factory()->create(['user_id' => $userId, 'site_id' => $siteId, 'dismissed_at' => now(), 'completed_at' => null]);
        EducationRecommendation::factory()->create(['user_id' => $userId, 'site_id' => $siteId, 'dismissed_at' => null, 'completed_at' => null]);

        $result = $this->service->userAnalytics($userId, $siteId);

        $this->assertEqualsWithDelta(0.75, $result['recommendation_acceptance_rate'], 0.001);
    }

    // --- T07 ---
    public function test_siteAnalytics_returns_required_keys(): void
    {
        $result = $this->service->siteAnalytics(null);

        $this->assertArrayHasKey('module_adoption', $result);
        $this->assertArrayHasKey('gap_distribution', $result);
        $this->assertArrayHasKey('readiness_distribution', $result);
    }

    // --- T08 ---
    public function test_siteAnalytics_module_adoption_counts_distinct_users(): void
    {
        $siteId = 10;

        foreach ([1, 2, 3] as $userId) {
            EducationFeatureUsage::factory()->create([
                'user_id'      => $userId,
                'site_id'      => $siteId,
                'module_slug'  => 'crm',
                'feature_slug' => 'contacts',
                'usage_count'  => 5,
            ]);
        }

        $result = $this->service->siteAnalytics($siteId);

        $this->assertArrayHasKey('crm', $result['module_adoption']);
        $this->assertSame(3, (int) $result['module_adoption']['crm']);
    }

    // --- T09 ---
    public function test_siteAnalytics_gap_distribution_has_all_priority_keys(): void
    {
        $result = $this->service->siteAnalytics(null);

        $this->assertArrayHasKey('Critical', $result['gap_distribution']);
        $this->assertArrayHasKey('High',     $result['gap_distribution']);
        $this->assertArrayHasKey('Medium',   $result['gap_distribution']);
        $this->assertArrayHasKey('Low',      $result['gap_distribution']);
    }

    // --- T10 ---
    public function test_siteAnalytics_readiness_distribution_has_correct_keys(): void
    {
        $result = $this->service->siteAnalytics(null);

        $dist = $result['readiness_distribution'];
        $this->assertArrayHasKey('average_score', $dist);
        $this->assertArrayHasKey('modules_above_75', $dist);
        $this->assertArrayHasKey('modules_below_25', $dist);
        $this->assertArrayHasKey('total_modules', $dist);
    }

    // --- T11 ---
    public function test_platformAnalytics_returns_required_keys(): void
    {
        $result = $this->service->platformAnalytics();

        $this->assertArrayHasKey('global_adoption', $result);
        $this->assertArrayHasKey('content_coverage_percent', $result);
        $this->assertArrayHasKey('recommendation_effectiveness', $result);
        $this->assertArrayHasKey('modules_with_content', $result);
        $this->assertArrayHasKey('total_modules', $result);
    }

    // --- T12 ---
    public function test_platformAnalytics_global_adoption_counts_unique_users(): void
    {
        foreach ([1, 2, 3] as $userId) {
            EducationFeatureUsage::factory()->create([
                'user_id'      => $userId,
                'site_id'      => $userId * 10,
                'module_slug'  => 'crm',
                'feature_slug' => 'contacts',
                'usage_count'  => 1,
            ]);
        }
        // Same user again — should not double-count
        EducationFeatureUsage::factory()->create([
            'user_id'      => 1,
            'site_id'      => 99,
            'module_slug'  => 'hr',
            'feature_slug' => 'employees',
            'usage_count'  => 1,
        ]);

        $result = $this->service->platformAnalytics();

        $this->assertSame(3, (int) $result['global_adoption']);
    }

    // --- T13 ---
    public function test_platformAnalytics_coverage_zero_when_no_articles(): void
    {
        EducationFeature::factory()->createMany([
            ['module_slug' => 'crm', 'site_id' => null],
            ['module_slug' => 'hr',  'site_id' => null],
        ]);

        $result = $this->service->platformAnalytics();

        $this->assertSame(0.0, $result['content_coverage_percent']);
    }

    // --- T14 ---
    public function test_platformAnalytics_effectiveness_zero_when_no_resolved_recs(): void
    {
        $result = $this->service->platformAnalytics();

        $this->assertSame(0.0, $result['recommendation_effectiveness']);
    }

    // --- T15 ---
    public function test_site_isolation_in_userAnalytics(): void
    {
        $userId = 7;
        $siteA  = 10;
        $siteB  = 20;

        // Recommendations on site B
        EducationRecommendation::factory()->create(['user_id' => $userId, 'site_id' => $siteB, 'completed_at' => now()]);
        EducationRecommendation::factory()->create(['user_id' => $userId, 'site_id' => $siteB, 'completed_at' => now()]);

        // userAnalytics for site A should show 0 recommendations
        $result = $this->service->userAnalytics($userId, $siteA);

        $this->assertSame(0, $result['recommendations']['total']);
    }
}
