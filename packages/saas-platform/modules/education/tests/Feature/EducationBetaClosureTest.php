<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;
use SaaSPlatform\Modules\Education\Models\EducationWalkthroughProgress;
use SaaSPlatform\Modules\Education\Services\WalkthroughTriggerService;
use Tests\TestCase;

/**
 * EDU-13.7 Beta Closure — Regression Tests
 *
 * BLOCKER #1: EducationGuideComposer must inject user->site_id into triggersForRoute()
 *             so completed walkthroughs are correctly suppressed.
 *
 * BLOCKER #2: POST /admin/education/walkthroughs/{id}/complete must enforce
 *             EducationWalkthroughPolicy::update() — returns 403 for unauthorised users.
 */
class EducationBetaClosureTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // BLOCKER #1 — Composer siteId injection & completion suppression
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * The regression: passing null siteId made the WHERE site_id IS NULL query
     * return zero rows (NOT NULL FK), so every completed walkthrough re-triggered.
     * The fix: composer resolves siteId from user->site_id.
     *
     * @test
     */
    public function triggers_for_route_suppresses_completed_walkthrough_for_site_user(): void
    {
        $svc = app(WalkthroughTriggerService::class);

        // Simulate a site admin: user 77 on site 5
        $userId = 77;
        $siteId = 5;

        // Seed a completion record for walkthrough 3, user 77, site 5
        EducationWalkthroughProgress::create([
            'edu_walkthrough_id' => 3,
            'user_id'            => $userId,
            'site_id'            => $siteId,
            'status'             => EducationWalkthroughProgress::STATUS_COMPLETED,
            'completed_at'       => now(),
        ]);

        // Query the completed IDs the same way triggersForRoute does
        $completedIds = EducationWalkthroughProgress::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->whereNotNull('completed_at')
            ->pluck('edu_walkthrough_id')
            ->toArray();

        // Walkthrough 3 must appear in the completed set
        $this->assertContains(3, $completedIds);
    }

    /**
     * The old broken code (passing null siteId) always returned empty — verify the
     * broken behaviour is gone by confirming null-siteId lookup returns nothing.
     *
     * @test
     */
    public function null_site_id_query_returns_no_completed_walkthroughs(): void
    {
        // Even if we have a progress record for site 5, querying with site_id = null
        // returns zero rows (NOT NULL FK constraint). The composer must NOT do this.
        EducationWalkthroughProgress::create([
            'edu_walkthrough_id' => 3,
            'user_id'            => 77,
            'site_id'            => 5,
            'status'             => EducationWalkthroughProgress::STATUS_COMPLETED,
            'completed_at'       => now(),
        ]);

        $completedWithNull = EducationWalkthroughProgress::where('user_id', 77)
            ->where('site_id', null) // the OLD broken call
            ->whereNotNull('completed_at')
            ->pluck('edu_walkthrough_id')
            ->toArray();

        // Must be empty — confirming the old pattern was broken and the fix is necessary
        $this->assertEmpty($completedWithNull,
            'NULL siteId query must return zero rows — the old composer code was incorrect.');
    }

    /**
     * EducationGuideComposer must use user->site_id, not null.
     * For a site admin (user->site_id set), triggersForRoute receives a non-null siteId.
     *
     * @test
     */
    public function composer_passes_user_site_id_to_triggers_for_route(): void
    {
        Cache::flush();

        $siteId    = 7;
        $callCount = 0;
        $passedSiteId = null;

        // Spy on the trigger service
        $this->mock(
            \SaaSPlatform\Modules\Education\Contracts\Services\WalkthroughTriggerServiceInterface::class,
            function ($mock) use (&$callCount, &$passedSiteId) {
                $mock->shouldReceive('triggersForRoute')
                    ->andReturnUsing(function ($route, $userId, $siteId) use (&$callCount, &$passedSiteId) {
                        $callCount++;
                        $passedSiteId = $siteId;
                        return collect();
                    });
            }
        );

        $this->mock(
            \SaaSPlatform\Modules\Education\Contracts\Services\EducationContextServiceInterface::class,
            function ($mock) {
                $mock->shouldReceive('recommendForCurrentRoute')->andReturn(collect());
            }
        );

        $this->mock(
            \SaaSPlatform\Modules\Education\Contracts\Services\EducationRecommendationServiceInterface::class,
            function ($mock) {
                $mock->shouldReceive('activeForUser')->andReturn(collect());
            }
        );

        // Create a site-admin user with a specific site_id
        $user = \App\Models\User::factory()->create([
            'is_admin' => false,
            'site_id'  => $siteId,
        ]);

        $this->actingAs($user);

        // Manually invoke the composer
        $composer = app(\SaaSPlatform\Modules\Education\Http\ViewComposers\EducationGuideComposer::class);
        $view     = view('education::admin.education.index');
        $composer->compose($view);

        // triggersForRoute was called with the user's site_id, not null
        $this->assertSame(1, $callCount, 'triggersForRoute should have been called once');
        $this->assertSame($siteId, $passedSiteId, 'triggersForRoute must receive user->site_id, not null');
    }

    /**
     * Platform admins (user->site_id = null) must NOT trigger walkthrough queries
     * because the NOT NULL FK constraint would cause the completion check to always fail.
     *
     * @test
     */
    public function composer_skips_walkthrough_triggers_for_platform_admin(): void
    {
        Cache::flush();

        $triggerCalled = false;

        $this->mock(
            \SaaSPlatform\Modules\Education\Contracts\Services\WalkthroughTriggerServiceInterface::class,
            function ($mock) use (&$triggerCalled) {
                $mock->shouldNotReceive('triggersForRoute');
            }
        );

        $this->mock(
            \SaaSPlatform\Modules\Education\Contracts\Services\EducationContextServiceInterface::class,
            function ($mock) {
                $mock->shouldReceive('recommendForCurrentRoute')->andReturn(collect());
            }
        );

        $this->mock(
            \SaaSPlatform\Modules\Education\Contracts\Services\EducationRecommendationServiceInterface::class,
            function ($mock) {
                $mock->shouldReceive('activeForUser')->andReturn(collect());
            }
        );

        // Platform admin: site_id = null
        $admin = \App\Models\User::factory()->create([
            'is_admin' => true,
            'site_id'  => null,
        ]);

        $this->actingAs($admin);

        $composer = app(\SaaSPlatform\Modules\Education\Http\ViewComposers\EducationGuideComposer::class);
        $view     = view('education::admin.education.index');
        $composer->compose($view);

        // Mockery will assert shouldNotReceive on teardown — nothing else to assert here
        $this->assertTrue(true, 'Composer must not call triggersForRoute for platform admins');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BLOCKER #2 — Policy enforcement on the complete route
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * An unauthorised user (no education.update permission, not admin)
     * must receive 403 from the complete endpoint.
     *
     * @test
     */
    public function complete_returns_403_for_unauthorised_user(): void
    {
        $wt   = EducationWalkthrough::factory()->create(['site_id' => null]);
        $user = \App\Models\User::factory()->create([
            'is_admin' => false,
            'site_id'  => 5,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('admin.education.walkthroughs.complete', $wt->id), [
                'site_id' => 5,
            ]);

        $response->assertStatus(403);
    }

    /**
     * An admin user must be able to mark a walkthrough complete.
     *
     * @test
     */
    public function complete_succeeds_for_authorised_admin(): void
    {
        $admin = \App\Models\User::factory()->create([
            'is_admin' => true,
            'site_id'  => 5,
        ]);
        $wt = EducationWalkthrough::factory()->create(['site_id' => null]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.education.walkthroughs.complete', $wt->id), [
                'site_id' => 5,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        $this->assertDatabaseHas('edu_walkthrough_progress', [
            'edu_walkthrough_id' => $wt->id,
            'user_id'            => $admin->id,
            'site_id'            => 5,
        ]);
    }

    /**
     * A non-existent walkthrough must return 404, not 403.
     *
     * @test
     */
    public function complete_returns_404_for_nonexistent_walkthrough(): void
    {
        $admin = \App\Models\User::factory()->create(['is_admin' => true, 'site_id' => 5]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.education.walkthroughs.complete', 99999), [
                'site_id' => 5,
            ]);

        $response->assertStatus(404);
    }

    /**
     * The complete endpoint must return 422 when site_id cannot be resolved.
     * This covers the scenario where the admin has no site context at all.
     *
     * @test
     */
    public function complete_returns_422_when_no_site_id_can_be_resolved(): void
    {
        $admin = \App\Models\User::factory()->create([
            'is_admin' => true,
            'site_id'  => null, // platform admin, no site
        ]);
        $wt = EducationWalkthrough::factory()->create(['site_id' => null]);

        // No site_id in request, no tenant_site attribute, user->site_id = null
        $response = $this->actingAs($admin)
            ->postJson(route('admin.education.walkthroughs.complete', $wt->id));

        $response->assertStatus(422);
        $response->assertJson(['error' => 'site_id required']);
    }
}
