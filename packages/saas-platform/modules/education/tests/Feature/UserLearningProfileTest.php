<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use SaaSPlatform\Modules\Education\Contracts\Services\UserLearningProfileServiceInterface;
use SaaSPlatform\Modules\Education\Dto\UserLearningProfileDto;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Models\EducationFeatureUsage;
use SaaSPlatform\Modules\Education\Models\EducationRecommendation;
use SaaSPlatform\Modules\Education\Models\EducationUserProgress;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;
use SaaSPlatform\Modules\Education\Models\EducationWalkthroughProgress;
use SaaSPlatform\Modules\Education\Services\Education\UserLearningProfileService;
use Tests\TestCase;

/**
 * EDU-14A — UserLearningProfile Engine Tests
 *
 * Coverage targets:
 *   - Zero-state user returns zero scores
 *   - Usage depth normalised against cap
 *   - Onboarding score based on walkthrough completion rate
 *   - Adoption score based on feature coverage
 *   - Engagement score weighted composite
 *   - Strongest / weakest module detection
 *   - 15-minute cache TTL enforced
 *   - Cache invalidation flushes stale profile
 *   - Overall score formula
 *   - Profile endpoint returns 401 for unauthenticated requests
 */
class UserLearningProfileTest extends TestCase
{
    use RefreshDatabase;

    private UserLearningProfileServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserLearningProfileServiceInterface::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Zero-state
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function zero_state_user_returns_all_zero_scores(): void
    {
        $profile = $this->service->buildProfile(999, null);

        $this->assertInstanceOf(UserLearningProfileDto::class, $profile);
        $this->assertEquals(0.0, $profile->skillScore);
        $this->assertEquals(0.0, $profile->adoptionScore);
        $this->assertEquals(0.0, $profile->engagementScore);
        $this->assertIsArray($profile->strongestModules);
        $this->assertIsArray($profile->weakestModules);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Skill score
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function skill_score_is_capped_at_100(): void
    {
        EducationFeatureUsage::create([
            'user_id'      => 1,
            'site_id'      => null,
            'module_slug'  => 'forms',
            'feature_slug' => 'create',
            'usage_count'  => 999, // far above cap
            'first_used_at'=> now(),
            'last_used_at' => now(),
        ]);

        $profile = $this->service->buildProfile(1, null);

        $this->assertLessThanOrEqual(100.0, $profile->skillScore);
        $this->assertEquals(100.0, $profile->skillScore);
    }

    /** @test */
    public function skill_score_is_proportional_to_usage_depth(): void
    {
        // usage_count=10 at cap of 20 → normalised = 0.5 → skill = 50.0
        EducationFeatureUsage::create([
            'user_id'      => 2,
            'site_id'      => null,
            'module_slug'  => 'tickets',
            'feature_slug' => 'view',
            'usage_count'  => 10,
            'first_used_at'=> now(),
            'last_used_at' => now(),
        ]);

        $profile = $this->service->buildProfile(2, null);

        $this->assertEquals(50.0, $profile->skillScore);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Onboarding score
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function onboarding_score_is_100_when_no_walkthroughs_registered(): void
    {
        $profile = $this->service->buildProfile(3, null);

        $this->assertEquals(100.0, $profile->onboardingScore);
    }

    /** @test */
    public function onboarding_score_reflects_walkthrough_completion_ratio(): void
    {
        $wt1 = EducationWalkthrough::create([
            'title'     => 'WT1', 'slug' => 'wt-1', 'is_active' => true, 'site_id' => null,
        ]);
        $wt2 = EducationWalkthrough::create([
            'title'     => 'WT2', 'slug' => 'wt-2', 'is_active' => true, 'site_id' => null,
        ]);

        // Complete only the first walkthrough
        EducationWalkthroughProgress::create([
            'user_id'            => 4,
            'site_id'            => null,
            'edu_walkthrough_id' => $wt1->id,
            'status'             => EducationWalkthroughProgress::STATUS_COMPLETED,
            'current_step'       => 1,
        ]);

        $profile = $this->service->buildProfile(4, null);

        // 1 of 2 completed → 50.0
        $this->assertEquals(50.0, $profile->onboardingScore);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Adoption score
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function adoption_score_is_proportion_of_features_used(): void
    {
        EducationFeature::create(['module_slug' => 'users', 'feature_slug' => 'list',   'feature_name' => 'List Users',   'site_id' => null]);
        EducationFeature::create(['module_slug' => 'users', 'feature_slug' => 'create', 'feature_name' => 'Create User',  'site_id' => null]);
        EducationFeature::create(['module_slug' => 'users', 'feature_slug' => 'delete', 'feature_name' => 'Delete User',  'site_id' => null]);
        EducationFeature::create(['module_slug' => 'users', 'feature_slug' => 'export', 'feature_name' => 'Export Users', 'site_id' => null]);

        // Use 2 of 4
        EducationFeatureUsage::create(['user_id' => 5, 'site_id' => null, 'module_slug' => 'users', 'feature_slug' => 'list',   'usage_count' => 1, 'first_used_at' => now(), 'last_used_at' => now()]);
        EducationFeatureUsage::create(['user_id' => 5, 'site_id' => null, 'module_slug' => 'users', 'feature_slug' => 'create', 'usage_count' => 1, 'first_used_at' => now(), 'last_used_at' => now()]);

        $profile = $this->service->buildProfile(5, null);

        $this->assertEquals(50.0, $profile->adoptionScore);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Engagement score
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function engagement_score_increases_with_recommendation_completions(): void
    {
        $baseline = $this->service->buildProfile(6, null);

        // Add 5 completed recommendations (hits the rec ceiling = 1.0)
        for ($i = 0; $i < 5; $i++) {
            EducationRecommendation::create([
                'user_id'             => 6,
                'site_id'             => null,
                'recommendation_type' => EducationRecommendation::TYPE_ARTICLE,
                'priority'            => 50,
                'reason'              => 'test',
                'completed_at'        => now(),
            ]);
        }

        $this->service->invalidate(6, null);
        $profile = $this->service->buildProfile(6, null);

        $this->assertGreaterThan($baseline->engagementScore, $profile->engagementScore);
        // rec component alone = 1.0 * 30 = 30
        $this->assertGreaterThanOrEqual(30.0, $profile->engagementScore);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Strongest / weakest modules
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function strongest_and_weakest_modules_reflect_usage_coverage(): void
    {
        EducationFeature::create(['module_slug' => 'alpha', 'feature_slug' => 'a1', 'feature_name' => 'A1', 'site_id' => null]);
        EducationFeature::create(['module_slug' => 'alpha', 'feature_slug' => 'a2', 'feature_name' => 'A2', 'site_id' => null]);
        EducationFeature::create(['module_slug' => 'beta',  'feature_slug' => 'b1', 'feature_name' => 'B1', 'site_id' => null]);
        EducationFeature::create(['module_slug' => 'beta',  'feature_slug' => 'b2', 'feature_name' => 'B2', 'site_id' => null]);

        // User 7 fully uses alpha (2/2) but not beta (0/2)
        EducationFeatureUsage::create(['user_id' => 7, 'site_id' => null, 'module_slug' => 'alpha', 'feature_slug' => 'a1', 'usage_count' => 5, 'first_used_at' => now(), 'last_used_at' => now()]);
        EducationFeatureUsage::create(['user_id' => 7, 'site_id' => null, 'module_slug' => 'alpha', 'feature_slug' => 'a2', 'usage_count' => 5, 'first_used_at' => now(), 'last_used_at' => now()]);

        $profile = $this->service->buildProfile(7, null);

        $this->assertContains('alpha', $profile->strongestModules);
        $this->assertContains('beta',  $profile->weakestModules);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Caching
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function profile_is_cached_for_15_minutes(): void
    {
        Cache::spy();

        $this->service->buildProfile(8, 10);

        Cache::shouldHaveReceived('remember')
            ->once()
            ->withArgs(fn($key, $ttl) => str_contains($key, 'edu_profile.8.10') && $ttl === 900);
    }

    /** @test */
    public function invalidate_removes_cached_profile(): void
    {
        $key = "edu_profile.9.null";
        Cache::put($key, 'stale-data', 900);

        $this->service->invalidate(9, null);

        $this->assertNull(Cache::get($key));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Overall score
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function overall_score_is_weighted_composite_of_four_dimensions(): void
    {
        $dto = new UserLearningProfileDto(
            userId:          1,
            siteId:          null,
            skillScore:      100.0,
            onboardingScore: 100.0,
            adoptionScore:   100.0,
            engagementScore: 100.0,
            strongestModules:[],
            weakestModules:  [],
            lastActivityAt:  null,
        );

        $this->assertEquals(100.0, $dto->overallScore());
    }

    /** @test */
    public function overall_score_partial_fills_weighted_correctly(): void
    {
        $dto = new UserLearningProfileDto(
            userId:          1,
            siteId:          null,
            skillScore:      80.0,   // * 0.25 = 20
            onboardingScore: 60.0,   // * 0.25 = 15
            adoptionScore:   50.0,   // * 0.30 = 15
            engagementScore: 40.0,   // * 0.20 = 8
            strongestModules:[],
            weakestModules:  [],
            lastActivityAt:  null,
        );

        // 20 + 15 + 15 + 8 = 58
        $this->assertEquals(58.0, $dto->overallScore());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // API endpoint
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function profile_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/education/profile');
        $response->assertStatus(401);
    }
}
