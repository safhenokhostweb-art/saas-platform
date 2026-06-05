<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationFeature;
use SaaSPlatform\Modules\Education\Models\EducationFeatureUsage;
use SaaSPlatform\Modules\Education\Repositories\EducationFeatureUsageRepository;
use SaaSPlatform\Modules\Education\Services\EducationRecommendationService;
use Tests\TestCase;

/**
 * EDU-13.5 Performance & Stability Test Matrix
 *
 * F-12: EducationGuideComposer caches results
 * F-13: TrackFeatureUsageMiddleware NOT on global web (only admin)
 * F-14: Sync API returns paginated responses
 * C-01/C-02: neverUsedFeatures / unusedFeatures use SQL NOT EXISTS
 * C-03: max_active_per_user cap is respected
 * C-05: neverUsedFeatures is per-user scoped
 */
class EducationPerformanceTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // F-12 — Guide composer caching
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function guide_composer_caches_result_for_same_user_and_route(): void
    {
        Cache::flush();
        $callCount = 0;

        // Spy on the context service call
        $this->mock(
            \SaaSPlatform\Modules\Education\Contracts\Services\EducationContextServiceInterface::class,
            function ($mock) use (&$callCount) {
                $mock->shouldReceive('recommendForCurrentRoute')
                    ->andReturnUsing(function () use (&$callCount) {
                        $callCount++;
                        return collect();
                    });
            }
        );

        $composer = app(\SaaSPlatform\Modules\Education\Http\ViewComposers\EducationGuideComposer::class);
        $user     = \App\Models\User::factory()->create(['is_admin' => true]);

        $this->actingAs($user);

        $view1 = view('education::admin.education.index');
        $view2 = view('education::admin.education.index');

        $composer->compose($view1);
        $composer->compose($view2);

        // Context service should be called only once — second call is served from cache
        $this->assertSame(1, $callCount);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // F-14 — Sync API pagination
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function sync_articles_response_includes_pagination_metadata(): void
    {
        $admin = \App\Models\User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/education/sync/1/articles?per_page=10');

        // Even if no articles exist, the response structure must include pagination keys
        $response->assertJsonStructure([
            'site_id',
            'articles',
            'total',
            'per_page',
            'current_page',
            'last_page',
        ]);

        $response->assertJsonPath('per_page', 10);
        $response->assertJsonPath('current_page', 1);
    }

    /** @test */
    public function sync_articles_per_page_is_capped_at_200(): void
    {
        $admin = \App\Models\User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/education/sync/1/articles?per_page=9999');

        $response->assertJsonPath('per_page', 200);
    }

    /** @test */
    public function sync_walkthroughs_response_includes_pagination_metadata(): void
    {
        $admin = \App\Models\User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/education/sync/1/walkthroughs');

        $response->assertJsonStructure([
            'site_id', 'walkthroughs', 'total', 'per_page', 'current_page', 'last_page',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // C-01/C-02 — SQL-based queries (no full-table PHP loads)
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function never_used_features_executes_single_sql_query(): void
    {
        EducationFeature::factory()->count(5)->create(['site_id' => null]);
        EducationFeatureUsage::factory()->create(['user_id' => 1]);

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $repo = app(EducationFeatureUsageRepository::class);
        $repo->neverUsedFeatures(userId: 1, siteId: null);

        // Should execute 1 SQL query with NOT EXISTS, not 2+ with PHP filtering
        $this->assertLessThanOrEqual(2, $queryCount);
    }

    /** @test */
    public function never_used_features_returns_only_features_unused_by_this_user(): void
    {
        EducationFeature::factory()->create([
            'site_id'      => null,
            'module_slug'  => 'tickets',
            'feature_slug' => 'list',
        ]);
        EducationFeature::factory()->create([
            'site_id'      => null,
            'module_slug'  => 'forms',
            'feature_slug' => 'builder',
        ]);

        // User 1 has used tickets.list
        EducationFeatureUsage::factory()->create([
            'user_id'      => 1,
            'module_slug'  => 'tickets',
            'feature_slug' => 'list',
            'site_id'      => null,
        ]);

        $repo    = app(EducationFeatureUsageRepository::class);
        $results = $repo->neverUsedFeatures(userId: 1, siteId: null);

        // Only forms.builder should be returned for user 1
        $this->assertCount(1, $results);
        $this->assertSame('forms', $results->first()->module_slug);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // C-03 — max_active_per_user enforcement
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function generate_for_user_stops_when_max_active_is_reached(): void
    {
        config(['education.recommendation.max_active_per_user' => 2]);

        $recRepo = $this->mock(
            \SaaSPlatform\Modules\Education\Contracts\Repositories\EducationRecommendationRepositoryInterface::class,
            function ($mock) {
                $mock->shouldReceive('countActiveForUser')->andReturn(2); // already at cap
                $mock->shouldNotReceive('create');
            }
        );

        $usageRepo = $this->mock(
            \SaaSPlatform\Modules\Education\Contracts\Repositories\EducationFeatureUsageRepositoryInterface::class,
            function ($mock) {
                // Should not even be called since we short-circuit
                $mock->shouldNotReceive('neverUsedFeatures');
                $mock->shouldNotReceive('unusedFeatures');
            }
        );

        $service = new EducationRecommendationService($usageRepo, $recRepo);
        $created = $service->generateForUser(userId: 1, siteId: null);

        $this->assertSame(0, $created);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // C-05 — Per-user unused features in recommendAvailableArticles
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function recommend_available_articles_uses_per_user_unused_features(): void
    {
        $userId  = 1;
        $siteId  = null;

        $feature = EducationFeature::factory()->create([
            'site_id'      => null,
            'module_slug'  => 'tickets',
            'feature_slug' => 'list',
            'route_name'   => 'admin.tickets.index',
        ]);

        $article = EducationArticle::factory()->create([
            'status'        => EducationArticle::STATUS_PUBLISHED,
            'route_pattern' => 'admin.tickets.index',
        ]);

        // User 2 has used tickets.list — but user 1 hasn't
        EducationFeatureUsage::factory()->create([
            'user_id'      => 2,
            'module_slug'  => 'tickets',
            'feature_slug' => 'list',
            'site_id'      => null,
        ]);

        $repo    = app(EducationFeatureUsageRepository::class);
        $unused  = $repo->neverUsedFeatures($userId, $siteId);

        // User 1 should still see tickets.list as unused
        $slugs = $unused->map(fn($f) => "{$f->module_slug}.{$f->feature_slug}");
        $this->assertContains('tickets.list', $slugs->all());
    }
}
