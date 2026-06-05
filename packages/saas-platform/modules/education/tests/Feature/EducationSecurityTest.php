<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationLearningPath;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;
use Tests\TestCase;

/**
 * EDU-13.5 Security Test Matrix
 *
 * F-02: Mobile API routes require auth:sanctum
 * F-03: show() enforces cross-tenant isolation
 * F-04: Sync API endpoints enforce site authorization
 * C-04: Admin controllers enforce policies
 */
class EducationSecurityTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // F-02 — Mobile API routes require authentication
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function mobile_articles_list_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/education/articles');
        $response->assertStatus(401);
    }

    /** @test */
    public function mobile_article_show_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/education/articles/1');
        $response->assertStatus(401);
    }

    /** @test */
    public function mobile_search_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/education/search?q=test');
        $response->assertStatus(401);
    }

    /** @test */
    public function mobile_learning_paths_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/education/learning-paths');
        $response->assertStatus(401);
    }

    /** @test */
    public function mobile_mark_progress_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/education/articles/1/progress');
        $response->assertStatus(401);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // F-03 — show() cross-tenant isolation
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function show_returns_404_when_article_belongs_to_different_site(): void
    {
        $user    = $this->createAuthenticatedUser();
        $article = EducationArticle::factory()->create([
            'site_id' => 999, // different tenant
            'status'  => EducationArticle::STATUS_PUBLISHED,
        ]);

        // The request's tenant_site resolves to site_id=1, but article is site_id=999
        $response = $this->actingAs($user, 'sanctum')
            ->withServerVariables(['tenant_site_id' => 1])
            ->getJson("/api/v1/education/articles/{$article->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function show_returns_article_when_site_id_matches(): void
    {
        $user    = $this->createAuthenticatedUser();
        $article = EducationArticle::factory()->create([
            'site_id' => null, // global article visible to all sites
            'status'  => EducationArticle::STATUS_PUBLISHED,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/education/articles/{$article->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $article->id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // F-04 — Sync API site authorization
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function sync_manifest_returns_403_for_wrong_site(): void
    {
        $user = $this->createNonAdminUserWithSite(siteId: 1);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/education/sync/99/manifest');

        $response->assertStatus(403);
    }

    /** @test */
    public function sync_articles_returns_403_for_wrong_site(): void
    {
        $user = $this->createNonAdminUserWithSite(siteId: 1);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/education/sync/99/articles');

        $response->assertStatus(403);
    }

    /** @test */
    public function sync_endpoint_allows_platform_admin_to_access_any_site(): void
    {
        $admin = $this->createAdminUser();

        // Admin accessing a different site should not 403
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/education/sync/99/manifest');

        // Will 404 if site doesn't exist, but not 403
        $this->assertNotEquals(403, $response->status());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // C-04 — Admin policy enforcement
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function article_index_forbidden_without_permission(): void
    {
        $user = $this->createUserWithoutEducationPermission();

        // Gate denies viewAny
        Gate::define('viewAny-' . EducationArticle::class, fn() => false);

        $response = $this->actingAs($user)
            ->get(route('admin.education.articles.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function article_destroy_forbidden_without_permission(): void
    {
        $user    = $this->createUserWithoutEducationPermission();
        $article = EducationArticle::factory()->create();

        $response = $this->actingAs($user)
            ->delete(route('admin.education.articles.destroy', $article));

        $response->assertStatus(403);
    }

    /** @test */
    public function walkthrough_create_forbidden_without_permission(): void
    {
        $user = $this->createUserWithoutEducationPermission();

        $response = $this->actingAs($user)
            ->get(route('admin.education.walkthroughs.create'));

        $response->assertStatus(403);
    }

    /** @test */
    public function learning_path_destroy_forbidden_without_permission(): void
    {
        $user = $this->createUserWithoutEducationPermission();
        $path = EducationLearningPath::factory()->create();

        $response = $this->actingAs($user)
            ->delete(route('admin.education.learning-paths.destroy', $path));

        $response->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function createAuthenticatedUser(): object
    {
        return \App\Models\User::factory()->create();
    }

    private function createAdminUser(): object
    {
        return \App\Models\User::factory()->create(['is_admin' => true]);
    }

    private function createNonAdminUserWithSite(int $siteId): object
    {
        $user = \App\Models\User::factory()->create(['is_admin' => false]);
        // Bind the tenant_site attribute that authorizeSite() checks
        request()->attributes->set('tenant_site', (object) ['id' => $siteId]);
        return $user;
    }

    private function createUserWithoutEducationPermission(): object
    {
        return \App\Models\User::factory()->create([
            'is_admin' => false,
            // No education.* permissions
        ]);
    }
}
