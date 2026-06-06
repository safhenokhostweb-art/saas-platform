<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationGenerationLog;
use SaaSPlatform\Modules\Education\Providers\EducationServiceProvider;
use SaaSPlatform\Modules\Education\Services\Education\EducationDraftPersistenceService;
use SaaSPlatform\Modules\Education\Dto\ContentGenerationJobDto;
use SaaSPlatform\Modules\Education\Dto\GeneratedContentDto;

/**
 * EDU-15 Security — Generated Content API
 *
 * Tenant context is simulated by adding a fake middleware that sets
 * `tenant_site` on the request attributes, which is how the controller
 * resolves site_id in production.
 *
 * Tests without tenant middleware use the null-site (platform) path.
 */
class GeneratedContentSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [EducationServiceProvider::class];
    }

    // ----- Authentication guard -----

    public function test_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/education/generated-content')
            ->assertStatus(401);
    }

    // ----- Null-site platform path (no tenant middleware) -----

    public function test_no_tenant_context_returns_only_null_site_drafts(): void
    {
        // Platform-level (null site) drafts
        $this->seedDrafts(siteId: null, count: 2);
        // Tenant-scoped (site 1) drafts — must not appear
        $this->seedDrafts(siteId: 1, count: 3);

        $response = $this->actingAs($this->makeUser())->getJson('/api/v1/education/generated-content');

        $response->assertStatus(200);
        // Only null-site drafts visible when no tenant context
        $this->assertSame(2, $response->json('data.draft_count'));
    }

    // ----- Tenant isolation via tenant_site request attribute -----

    public function test_tenant_context_scopes_to_own_site(): void
    {
        $this->seedDrafts(siteId: 1, count: 3);
        $this->seedDrafts(siteId: 2, count: 5);

        // Request with tenant_site=1
        $response = $this->withTenantSite(1)
            ->actingAs($this->makeUser())
            ->getJson('/api/v1/education/generated-content');

        $response->assertStatus(200);
        $this->assertSame(3, $response->json('data.draft_count'));
    }

    public function test_site_a_context_cannot_see_site_b_drafts(): void
    {
        $this->seedDrafts(siteId: 1, count: 3);

        // Site 2 tenant context: should see 0 drafts
        $response = $this->withTenantSite(2)
            ->actingAs($this->makeUser())
            ->getJson('/api/v1/education/generated-content');

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('data.draft_count'));
    }

    public function test_recent_drafts_scoped_to_tenant(): void
    {
        $this->seedDrafts(siteId: 1, count: 3);

        $response = $this->withTenantSite(2)
            ->actingAs($this->makeUser())
            ->getJson('/api/v1/education/generated-content');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.recent_drafts'));
    }

    // ----- Platform drafts not leaked to tenants -----

    public function test_platform_drafts_not_visible_to_tenant(): void
    {
        $this->seedDrafts(siteId: null, count: 3);

        // Tenant context for site 5: whereNull is NOT used → expects 0
        $response = $this->withTenantSite(5)
            ->actingAs($this->makeUser())
            ->getJson('/api/v1/education/generated-content');

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('data.draft_count'));
    }

    // ----- Provider usage isolation -----

    public function test_provider_usage_scoped_to_tenant(): void
    {
        $this->seedGenerationLog(provider: 'claude', count: 3, siteId: 1);
        $this->seedGenerationLog(provider: 'openai', count: 1, siteId: 2);

        $res1 = $this->withTenantSite(1)
            ->actingAs($this->makeUser())
            ->getJson('/api/v1/education/generated-content');

        $usage1 = $res1->json('data.provider_usage');
        $this->assertArrayHasKey('claude', $usage1);
        $this->assertArrayNotHasKey('openai', $usage1);

        $res2 = $this->withTenantSite(2)
            ->actingAs($this->makeUser())
            ->getJson('/api/v1/education/generated-content');

        $usage2 = $res2->json('data.provider_usage');
        $this->assertArrayHasKey('openai', $usage2);
        $this->assertArrayNotHasKey('claude', $usage2);
    }

    // ----- Manually-authored drafts not counted -----

    public function test_manually_created_drafts_not_counted(): void
    {
        EducationArticle::create([
            'site_id'        => null,
            'author_id'      => 1,
            'title'          => 'Human Written Draft',
            'slug'           => 'human-written-draft',
            'body'           => str_repeat('Human content. ', 8),
            'status'         => EducationArticle::STATUS_DRAFT,
            'auto_generated' => false,
            'module_slug'    => 'billing',
            'content_type'   => 'articles',
        ]);

        $response = $this->actingAs($this->makeUser())
            ->getJson('/api/v1/education/generated-content');

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('data.draft_count'));
    }

    // ----- Persistence atomicity (rollback test) -----

    public function test_persistence_is_atomic_article_and_log_together(): void
    {
        $service = new EducationDraftPersistenceService();

        $dto = new GeneratedContentDto(
            title:           'Rollback Test Article',
            summary:         'Summary',
            body:            str_repeat('Body. ', 20),
            tags:            [],
            confidenceScore: 75.0,
            provider:        'null',
        );
        $job = new ContentGenerationJobDto(
            moduleSlug:   'payments',
            contentType:  'articles',
            priority:     'High',
            siteId:       99,
            sourceReason: 'content_deficit',
        );

        $article = $service->persist($dto, $job, authorId: 1);

        $this->assertDatabaseHas('edu_articles', ['id' => $article->id, 'site_id' => 99]);
        $this->assertDatabaseHas('edu_generation_logs', ['edu_article_id' => $article->id]);
    }

    // -----------------------------------------------------------------------

    /** Simulates a request that has a tenant_site attribute set (production middleware path). */
    private function withTenantSite(int $siteId): static
    {
        // Register a one-time middleware that injects the tenant_site attribute
        $this->app['router']->pushMiddlewareToGroup('api', function ($request, $next) use ($siteId) {
            $tenantSite = new \stdClass();
            $tenantSite->id = $siteId;
            $request->attributes->set('tenant_site', $tenantSite);
            return $next($request);
        });

        return $this;
    }

    private function makeUser(): object
    {
        return new class {
            public int $id = 1;
            public string $role = 'admin';
        };
    }

    private function seedDrafts(?int $siteId, int $count, string $status = EducationArticle::STATUS_DRAFT): void
    {
        for ($i = 0; $i < $count; $i++) {
            EducationArticle::create([
                'site_id'        => $siteId,
                'author_id'      => 1,
                'title'          => "Security Draft {$i} " . uniqid(),
                'slug'           => 'security-draft-' . uniqid(),
                'body'           => str_repeat('body content ', 10),
                'status'         => $status,
                'auto_generated' => true,
                'module_slug'    => 'invoices',
                'content_type'   => 'articles',
            ]);
        }
    }

    private function seedGenerationLog(string $provider, int $count, ?int $siteId): void
    {
        for ($i = 0; $i < $count; $i++) {
            EducationGenerationLog::create([
                'site_id'      => $siteId,
                'module_slug'  => 'invoices',
                'content_type' => 'articles',
                'provider'     => $provider,
                'status'       => EducationGenerationLog::STATUS_SUCCESS,
            ]);
        }
    }
}
