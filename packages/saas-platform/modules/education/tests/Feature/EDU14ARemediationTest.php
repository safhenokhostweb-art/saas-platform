<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\Modules\Education\Contracts\Services\AIRecommendationScoreServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\KnowledgeGapServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\UserLearningProfileServiceInterface;
use SaaSPlatform\Modules\Education\Dto\UserLearningProfileDto;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationCategory;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Models\EducationFeatureUsage;
use SaaSPlatform\Modules\Education\Models\EducationRecommendation;
use SaaSPlatform\Modules\Education\Models\EducationUserProgress;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;
use SaaSPlatform\Modules\Education\Models\EducationWalkthroughProgress;
use Tests\TestCase;

/**
 * EDU-14A.1 Remediation — Targeted regression tests
 *
 * F-03: KnowledgeGapService — cross-tenant usage isolation when siteId=null
 * F-04: AIRecommendationScoreService — cross-tenant usage isolation when siteId=null
 * F-05: UserLearningProfileService::resolveLastActivity — null-site scope correctness
 * PA-02: UserLearningProfileService::computeOnboardingScore — denominator/numerator parity
 * PA-07: KnowledgeGapService::completionRatesByModule — excludes soft-deleted articles
 * PA-09: KnowledgeGapService — 15-minute cache with invalidation
 */
class EDU14ARemediationTest extends TestCase
{
    use RefreshDatabase;

    private KnowledgeGapServiceInterface         $gaps;
    private AIRecommendationScoreServiceInterface $scorer;
    private UserLearningProfileServiceInterface   $profiles;
    private int $catId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gaps     = app(KnowledgeGapServiceInterface::class);
        $this->scorer   = app(AIRecommendationScoreServiceInterface::class);
        $this->profiles = app(UserLearningProfileServiceInterface::class);

        $cat = EducationCategory::create(['name' => 'Remediation', 'slug' => 'remediation', 'site_id' => null]);
        $this->catId = $cat->id;
    }

    // =========================================================================
    // F-03 — KnowledgeGapService: cross-tenant isolation when siteId=null
    // =========================================================================

    /** @test */
    public function f03_null_site_gap_only_sees_null_site_usage(): void
    {
        // Global (null-site) feature for user 1
        EducationFeature::create(['module_slug' => 'global_mod', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);

        // User 1 has null-site usage (should be counted)
        EducationFeatureUsage::create([
            'user_id' => 1, 'site_id' => null,
            'module_slug' => 'global_mod', 'feature_slug' => 'f1',
            'usage_count' => 5, 'first_used_at' => now(), 'last_used_at' => now(),
        ]);

        // Same user, same module — but for tenant site 99 (must NOT be counted in null-site context)
        EducationFeatureUsage::create([
            'user_id' => 1, 'site_id' => 99,
            'module_slug' => 'global_mod', 'feature_slug' => 'f1',
            'usage_count' => 50, // very high — would corrupt score if leaking
            'first_used_at' => now(), 'last_used_at' => now(),
        ]);

        $gaps       = $this->gaps->detectForUser(1, null);
        $gap        = $gaps->firstWhere('module_slug', 'global_mod');
        $usageScore = (5 / 1) * 100; // 1 feature used out of 1 = 100%

        // F-03: usage_score must be 100 (from null-site record only, not 50+5=55 combined)
        // With usage_score=100, docs=0, completion=0 → gap = 100 - (100*0.40) = 60.0
        $this->assertNotNull($gap);
        $this->assertEquals(60.0, $gap['gap_score']);
    }

    /** @test */
    public function f03_tenant_site_gap_only_sees_that_tenant_usage(): void
    {
        EducationFeature::create(['module_slug' => 'tenant_mod', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => 5]);

        // User 2 has usage for site 5 (correct)
        EducationFeatureUsage::create([
            'user_id' => 2, 'site_id' => 5,
            'module_slug' => 'tenant_mod', 'feature_slug' => 'f1',
            'usage_count' => 3, 'first_used_at' => now(), 'last_used_at' => now(),
        ]);

        // Same user, same module — different tenant (site 99) must not bleed into site 5 context
        EducationFeatureUsage::create([
            'user_id' => 2, 'site_id' => 99,
            'module_slug' => 'tenant_mod', 'feature_slug' => 'f1',
            'usage_count' => 100,
            'first_used_at' => now(), 'last_used_at' => now(),
        ]);

        $gaps = $this->gaps->detectForUser(2, 5);
        $gap  = $gaps->firstWhere('module_slug', 'tenant_mod');

        // F-03: Only site-5 usage counted → 1/1 feature used = 100% → gap = 60.0
        $this->assertNotNull($gap);
        $this->assertEquals(60.0, $gap['gap_score']);
    }

    /** @test */
    public function f03_null_site_and_tenant_contexts_produce_different_gap_scores(): void
    {
        EducationFeature::create(['module_slug' => 'shared_mod', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);

        // User 3 has only tenant usage (site 7), no global usage
        EducationFeatureUsage::create([
            'user_id' => 3, 'site_id' => 7,
            'module_slug' => 'shared_mod', 'feature_slug' => 'f1',
            'usage_count' => 10, 'first_used_at' => now(), 'last_used_at' => now(),
        ]);

        $nullGap   = $this->gaps->detectForUser(3, null)->firstWhere('module_slug', 'shared_mod');
        $tenantGap = $this->gaps->detectForUser(3, 7)->firstWhere('module_slug', 'shared_mod');

        // F-03: null-site context sees NO usage → gap=100; tenant-7 context sees usage → gap<100
        $this->assertEquals(100.0, $nullGap['gap_score']);
        $this->assertLessThan(100.0, $tenantGap['gap_score']);
    }

    // =========================================================================
    // F-04 — AIRecommendationScoreService: cross-tenant isolation when siteId=null
    // =========================================================================

    /** @test */
    public function f04_null_site_usage_map_excludes_tenant_rows(): void
    {
        // User 10 has null-site usage (should appear in null-site scorer)
        EducationFeatureUsage::create([
            'user_id' => 10, 'site_id' => null,
            'module_slug' => 'null_mod', 'feature_slug' => 'f1',
            'usage_count' => 5, 'first_used_at' => now(), 'last_used_at' => now(),
        ]);

        // Same user — tenant usage that must NOT contaminate null-site scoring
        EducationFeatureUsage::create([
            'user_id' => 10, 'site_id' => 88,
            'module_slug' => 'null_mod', 'feature_slug' => 'f1',
            'usage_count' => 999,
            'first_used_at' => now()->subDays(40), 'last_used_at' => now()->subDays(40),
        ]);

        $cat = EducationCategory::create(['name' => 'NM', 'slug' => 'nm', 'site_id' => null]);
        $art = EducationArticle::create(['title' => 'NM', 'slug' => 'nm-a', 'body' => 'body', 'module_slug' => 'null_mod', 'status' => 'published', 'edu_category_id' => $cat->id]);
        $rec = EducationRecommendation::create(['user_id' => 10, 'site_id' => null, 'recommendation_type' => 'article', 'article_id' => $art->id, 'priority' => 50, 'reason' => 'r']);
        $rec->setRelation('article', $art);

        $profile = $this->makeProfile(10, null);
        $result  = $this->scorer->score(collect([$rec]), $profile, collect());

        // F-04: usage_component reflects null-site usage (count=5), not contaminated by site-88 (count=999)
        // With site-88 leaking, total_usage=1004 → normalised=1.0 → usage_component=0
        // With only null-site: total_usage=5 → normalised=0.1 → usage_component=90.0
        $usageComponent = $result[0]['score_breakdown']['usage_component'];
        $this->assertGreaterThan(0.0, $usageComponent,
            'F-04 regression: tenant usage leaked, usage_component should be > 0 for null-site context'
        );
        $this->assertEquals(90.0, $usageComponent);
    }

    /** @test */
    public function f04_null_site_progress_map_excludes_tenant_rows(): void
    {
        $cat  = EducationCategory::create(['name' => 'PM2', 'slug' => 'pm2', 'site_id' => null]);
        $art  = EducationArticle::create([
            'title' => 'PM2', 'slug' => 'pm2-a', 'body' => 'body',
            'module_slug' => 'progress_mod', 'status' => 'published',
            'edu_category_id' => $cat->id,
        ]);

        // User 10 has NO null-site article progress
        // But DOES have tenant progress (site 77) at 100% — must not bleed into null-site context
        EducationUserProgress::create([
            'user_id'          => 10,
            'site_id'          => 77,
            'progressable_id'  => $art->id,
            'progressable_type'=> 'SaaSPlatform\Modules\Education\Models\EducationArticle',
            'status'           => EducationUserProgress::STATUS_COMPLETED,
            'progress_percent' => 100,
        ]);

        $rec = EducationRecommendation::create(['user_id' => 10, 'site_id' => null, 'recommendation_type' => 'article', 'article_id' => $art->id, 'priority' => 50, 'reason' => 'r']);
        $rec->setRelation('article', $art);

        $profile = $this->makeProfile(10, null);
        $result  = $this->scorer->score(collect([$rec]), $profile, collect());

        // F-04: null-site progress map has 0% for progress_mod → progress_component = 100
        // If tenant progress leaked: avg_progress=100 → progress_component = 0
        $progressComponent = $result[0]['score_breakdown']['progress_component'];
        $this->assertEquals(100.0, $progressComponent,
            'F-04 regression: tenant progress leaked into null-site scoring context'
        );
    }

    // =========================================================================
    // F-05 — UserLearningProfileService::resolveLastActivity null-site isolation
    // =========================================================================

    /** @test */
    public function f05_last_activity_for_null_site_only_uses_null_site_records(): void
    {
        // User 20 has null-site activity (old — 10 days ago)
        EducationFeatureUsage::create([
            'user_id' => 20, 'site_id' => null,
            'module_slug' => 'la_mod', 'feature_slug' => 'f1',
            'usage_count' => 1,
            'first_used_at' => now()->subDays(10),
            'last_used_at'  => now()->subDays(10),
        ]);

        // Same user — tenant usage from yesterday (more recent, must NOT bleed into null-site context)
        EducationFeatureUsage::create([
            'user_id' => 20, 'site_id' => 55,
            'module_slug' => 'la_mod', 'feature_slug' => 'f1',
            'usage_count' => 1,
            'first_used_at' => now()->subDays(1),
            'last_used_at'  => now()->subDays(1),
        ]);

        $profile = $this->profiles->buildProfile(20, null);

        // F-05: lastActivityAt should reflect ~10 days ago, NOT ~1 day ago (tenant leak)
        $this->assertNotNull($profile->lastActivityAt);
        $daysSince = now()->diffInDays($profile->lastActivityAt);
        $this->assertGreaterThanOrEqual(9, $daysSince,
            'F-05 regression: tenant last_used_at leaked into null-site profile (too recent)'
        );
    }

    /** @test */
    public function f05_last_activity_for_tenant_site_only_uses_that_site_records(): void
    {
        // User 21 has tenant-site 6 activity (yesterday)
        EducationFeatureUsage::create([
            'user_id' => 21, 'site_id' => 6,
            'module_slug' => 'ta_mod', 'feature_slug' => 'f1',
            'usage_count' => 1,
            'first_used_at' => now()->subDays(1),
            'last_used_at'  => now()->subDays(1),
        ]);

        // Same user — null-site activity (a month ago, must NOT bleed into site-6 context)
        EducationFeatureUsage::create([
            'user_id' => 21, 'site_id' => null,
            'module_slug' => 'ta_mod', 'feature_slug' => 'f1',
            'usage_count' => 1,
            'first_used_at' => now()->subDays(30),
            'last_used_at'  => now()->subDays(30),
        ]);

        $profile = $this->profiles->buildProfile(21, 6);

        $this->assertNotNull($profile->lastActivityAt);
        $daysSince = now()->diffInDays($profile->lastActivityAt);
        // F-05: Should reflect ~1 day ago (site 6 record), not ~30 days ago (null-site)
        $this->assertLessThanOrEqual(2, $daysSince,
            'F-05 regression: null-site last_used_at replaced tenant activity'
        );
    }

    /** @test */
    public function f05_last_activity_returns_null_when_no_null_site_usage_exists(): void
    {
        // User 22 only has tenant usage — null-site profile should have no activity
        EducationFeatureUsage::create([
            'user_id' => 22, 'site_id' => 9,
            'module_slug' => 'x_mod', 'feature_slug' => 'f1',
            'usage_count' => 5,
            'first_used_at' => now(), 'last_used_at' => now(),
        ]);

        $profile = $this->profiles->buildProfile(22, null);

        // F-05: null-site profile with no null-site usage → null lastActivityAt
        $this->assertNull($profile->lastActivityAt);
    }

    // =========================================================================
    // PA-02 — computeOnboardingScore: denominator/numerator parity
    // =========================================================================

    /** @test */
    public function pa02_null_site_onboarding_only_counts_null_site_walkthroughs(): void
    {
        // 2 global walkthroughs
        $wt1 = EducationWalkthrough::create(['title' => 'WT1', 'slug' => 'wt-pa02-1', 'is_active' => true, 'site_id' => null]);
        $wt2 = EducationWalkthrough::create(['title' => 'WT2', 'slug' => 'wt-pa02-2', 'is_active' => true, 'site_id' => null]);

        // 1 tenant walkthrough — must NOT inflate the denominator for null-site context
        EducationWalkthrough::create(['title' => 'WT3', 'slug' => 'wt-pa02-3', 'is_active' => true, 'site_id' => 30]);

        // User 30 completes 1 of the 2 global walkthroughs
        EducationWalkthroughProgress::create([
            'user_id'            => 30, 'site_id' => null,
            'edu_walkthrough_id' => $wt1->id,
            'status'             => EducationWalkthroughProgress::STATUS_COMPLETED,
            'current_step'       => 1,
        ]);

        $profile = $this->profiles->buildProfile(30, null);

        // PA-02: denominator = 2 (null-site only), not 3 (all sites)
        // completed = 1 → 1/2 = 50.0
        $this->assertEquals(50.0, $profile->onboardingScore,
            'PA-02 regression: tenant walkthrough inflated denominator'
        );
    }

    /** @test */
    public function pa02_tenant_site_onboarding_counts_global_plus_site_specific_walkthroughs(): void
    {
        // 1 global walkthrough
        $wtGlobal = EducationWalkthrough::create(['title' => 'WTG', 'slug' => 'wt-pa02-g', 'is_active' => true, 'site_id' => null]);
        // 1 site-specific walkthrough
        $wtSite   = EducationWalkthrough::create(['title' => 'WTS', 'slug' => 'wt-pa02-s', 'is_active' => true, 'site_id' => 40]);

        // User 40 completes both
        EducationWalkthroughProgress::create([
            'user_id' => 40, 'site_id' => null,
            'edu_walkthrough_id' => $wtGlobal->id,
            'status' => EducationWalkthroughProgress::STATUS_COMPLETED,
            'current_step' => 1,
        ]);
        EducationWalkthroughProgress::create([
            'user_id' => 40, 'site_id' => 40,
            'edu_walkthrough_id' => $wtSite->id,
            'status' => EducationWalkthroughProgress::STATUS_COMPLETED,
            'current_step' => 1,
        ]);

        $profile = $this->profiles->buildProfile(40, 40);

        // PA-02: denominator = 2 (global + site-40), completed = 2 → 100.0
        $this->assertEquals(100.0, $profile->onboardingScore,
            'PA-02: completing both global and site-specific walkthroughs should give 100'
        );
    }

    /** @test */
    public function pa02_completed_global_walkthrough_counts_in_tenant_context(): void
    {
        $wtGlobal = EducationWalkthrough::create(['title' => 'WTG2', 'slug' => 'wt-pa02-g2', 'is_active' => true, 'site_id' => null]);
        $wtSite   = EducationWalkthrough::create(['title' => 'WTS2', 'slug' => 'wt-pa02-s2', 'is_active' => true, 'site_id' => 50]);

        // User 50 only completes the global walkthrough
        EducationWalkthroughProgress::create([
            'user_id' => 50, 'site_id' => null,
            'edu_walkthrough_id' => $wtGlobal->id,
            'status' => EducationWalkthroughProgress::STATUS_COMPLETED,
            'current_step' => 1,
        ]);

        $profile = $this->profiles->buildProfile(50, 50);

        // PA-02: total=2 (global+site-50), completed=1 (global) → 50.0
        // Old bug: completed only counted site_id=50 records → 0/2=0
        $this->assertEquals(50.0, $profile->onboardingScore,
            'PA-02 regression: global walkthrough completion not credited in tenant context'
        );
    }

    // =========================================================================
    // PA-07 — KnowledgeGapService: soft-deleted articles excluded from completion rate
    // =========================================================================

    /** @test */
    public function pa07_soft_deleted_articles_excluded_from_completion_rate(): void
    {
        EducationFeature::create(['module_slug' => 'del_mod', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);

        // Create an article, record progress, then soft-delete it
        $deletedArticle = EducationArticle::create([
            'title' => 'Deleted', 'slug' => 'deleted-pa07', 'body' => 'body',
            'module_slug' => 'del_mod', 'status' => 'published',
            'edu_category_id' => $this->catId,
        ]);

        EducationUserProgress::create([
            'user_id'          => 60, 'site_id' => null,
            'progressable_id'  => $deletedArticle->id,
            'progressable_type'=> 'SaaSPlatform\Modules\Education\Models\EducationArticle',
            'status'           => EducationUserProgress::STATUS_COMPLETED,
            'progress_percent' => 100,
        ]);

        // Soft-delete the article
        $deletedArticle->delete();

        // Live article — NOT completed by user
        $liveArticle = EducationArticle::create([
            'title' => 'Live', 'slug' => 'live-pa07', 'body' => 'body',
            'module_slug' => 'del_mod', 'status' => 'published',
            'edu_category_id' => $this->catId,
        ]);

        $gaps = $this->gaps->detectForUser(60, null);
        $gap  = $gaps->firstWhere('module_slug', 'del_mod');

        // PA-07: Only the live article is counted. No completion for live article → completion_rate=0
        // If soft-deleted leaked: done=1, total=1 → completion_rate=100 → lower gap
        // Without leak: done=0, total=1 → completion_rate=0 → higher gap
        // gap = 100 - (usage*0.4 + 0*0.35 + docs*0.25)
        // usage=0, docs=1/2*100=50 → gap = 100 - (0 + 0 + 50*0.25) = 87.5
        $this->assertNotNull($gap);
        $this->assertEquals(87.5, $gap['gap_score'],
            'PA-07 regression: soft-deleted article progress inflated completion rate'
        );
    }

    /** @test */
    public function pa07_completion_rate_uses_only_live_articles(): void
    {
        EducationFeature::create(['module_slug' => 'live_only', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);

        $live = EducationArticle::create([
            'title' => 'Live2', 'slug' => 'live2-pa07', 'body' => 'body',
            'module_slug' => 'live_only', 'status' => 'published',
            'edu_category_id' => $this->catId,
        ]);

        // Complete the live article
        EducationUserProgress::create([
            'user_id'          => 61, 'site_id' => null,
            'progressable_id'  => $live->id,
            'progressable_type'=> 'SaaSPlatform\Modules\Education\Models\EducationArticle',
            'status'           => EducationUserProgress::STATUS_COMPLETED,
            'progress_percent' => 100,
        ]);

        // Also a deleted article with progress
        $dead = EducationArticle::create([
            'title' => 'Dead', 'slug' => 'dead-pa07', 'body' => 'body',
            'module_slug' => 'live_only', 'status' => 'published',
            'edu_category_id' => $this->catId,
        ]);
        EducationUserProgress::create([
            'user_id'          => 61, 'site_id' => null,
            'progressable_id'  => $dead->id,
            'progressable_type'=> 'SaaSPlatform\Modules\Education\Models\EducationArticle',
            'status'           => EducationUserProgress::STATUS_COMPLETED,
            'progress_percent' => 100,
        ]);
        $dead->delete();

        $gaps = $this->gaps->detectForUser(61, null);
        $gap  = $gaps->firstWhere('module_slug', 'live_only');

        // PA-07: only the live article counts → done=1/total=1 → completion=100
        // With deletion excluded, completion rate = 100% for the one live article
        // gap = 100 - (0*0.4 + 100*0.35 + docs*0.25)
        // docs: 1 live article → score=50 → gap = 100 - (0 + 35 + 12.5) = 52.5
        $this->assertNotNull($gap);
        $this->assertEquals(52.5, $gap['gap_score']);
    }

    // =========================================================================
    // PA-09 — KnowledgeGapService: 15-min cache with user+site specificity
    // =========================================================================

    /** @test */
    public function pa09_gaps_are_cached_for_15_minutes(): void
    {
        Cache::spy();

        $this->gaps->detectForUser(70, 10);

        Cache::shouldHaveReceived('remember')
            ->once()
            ->withArgs(fn($key, $ttl) => $key === 'edu_gap.70.10' && $ttl === 900);
    }

    /** @test */
    public function pa09_cache_key_includes_user_id_and_site_id(): void
    {
        Cache::spy();

        $this->gaps->detectForUser(71, null);

        Cache::shouldHaveReceived('remember')
            ->once()
            ->withArgs(fn($key) => $key === 'edu_gap.71.null');
    }

    /** @test */
    public function pa09_cache_is_user_specific(): void
    {
        EducationFeature::create(['module_slug' => 'cache_mod', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);

        // User 80: no usage → high gap
        $gapUser80 = $this->gaps->detectForUser(80, null)->firstWhere('module_slug', 'cache_mod');

        // User 81: has usage → lower gap
        EducationFeatureUsage::create([
            'user_id' => 81, 'site_id' => null,
            'module_slug' => 'cache_mod', 'feature_slug' => 'f1',
            'usage_count' => 5, 'first_used_at' => now(), 'last_used_at' => now(),
        ]);
        $gapUser81 = $this->gaps->detectForUser(81, null)->firstWhere('module_slug', 'cache_mod');

        // PA-09: different users produce different cached results
        $this->assertNotEquals($gapUser80['gap_score'], $gapUser81['gap_score']);
    }

    /** @test */
    public function pa09_cache_is_site_specific(): void
    {
        EducationFeature::create(['module_slug' => 'site_cache', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => 20]);
        EducationFeature::create(['module_slug' => 'global_cache', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);

        $gapSite20 = $this->gaps->detectForUser(82, 20);
        $gapNull   = $this->gaps->detectForUser(82, null);

        // PA-09: different sites produce different gap collections
        $site20Modules = $gapSite20->pluck('module_slug')->sort()->values();
        $nullModules   = $gapNull->pluck('module_slug')->sort()->values();

        // site 20 includes both global + site-20 modules; null includes only global
        $this->assertTrue($site20Modules->contains('site_cache'));
        $this->assertFalse($nullModules->contains('site_cache'));
    }

    /** @test */
    public function pa09_invalidate_clears_the_cached_entry(): void
    {
        $key = 'edu_gap.90.null';
        Cache::put($key, collect([['module_slug' => 'stale', 'gap_score' => 99.0, 'priority' => 'Critical']]), 900);

        $this->gaps->invalidate(90, null);

        $this->assertNull(Cache::get($key));
    }

    /** @test */
    public function pa09_invalidate_is_scoped_to_user_and_site(): void
    {
        Cache::put('edu_gap.91.10',   'data_a', 900);
        Cache::put('edu_gap.91.null', 'data_b', 900);
        Cache::put('edu_gap.92.10',   'data_c', 900);

        // Invalidate only user 91 / site 10
        $this->gaps->invalidate(91, 10);

        $this->assertNull(Cache::get('edu_gap.91.10'),   'Target entry should be cleared');
        $this->assertEquals('data_b', Cache::get('edu_gap.91.null'), 'Other site for same user untouched');
        $this->assertEquals('data_c', Cache::get('edu_gap.92.10'),   'Other user untouched');
    }

    /** @test */
    public function pa09_second_call_returns_cached_result_without_recomputing(): void
    {
        EducationFeature::create(['module_slug' => 'cached_once', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);

        // First call — computes and caches
        $first = $this->gaps->detectForUser(93, null);

        // Add more features — should NOT appear in second call (cache hit)
        EducationFeature::create(['module_slug' => 'new_after_cache', 'feature_slug' => 'f1', 'feature_name' => 'F1', 'site_id' => null]);

        // Second call — should return cached result
        $second = $this->gaps->detectForUser(93, null);

        $this->assertEquals($first->toArray(), $second->toArray());
        $this->assertFalse($second->pluck('module_slug')->contains('new_after_cache'));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeProfile(int $userId, ?int $siteId): UserLearningProfileDto
    {
        return new UserLearningProfileDto(
            userId:          $userId,
            siteId:          $siteId,
            skillScore:      0.0,
            onboardingScore: 0.0,
            adoptionScore:   0.0,
            engagementScore: 0.0,
            strongestModules:[],
            weakestModules:  [],
            lastActivityAt:  null,
        );
    }
}
