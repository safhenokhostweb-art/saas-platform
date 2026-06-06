<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationGenerationLog;
use SaaSPlatform\Modules\Education\Providers\EducationServiceProvider;

/**
 * EDU-15 Phase 6 — GET /api/v1/education/generated-content
 *
 * Tests API shape, count accuracy, and site isolation.
 */
class GeneratedContentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [EducationServiceProvider::class];
    }

    // ----- Authentication -----

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/education/generated-content');
        $response->assertStatus(401);
    }

    // ----- Response shape -----

    public function test_authenticated_response_has_required_keys(): void
    {
        $user = $this->createUser();
        $response = $this->actingAs($user)->getJson('/api/v1/education/generated-content');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'draft_count',
                    'approved_count',
                    'rejected_count',
                    'provider_usage',
                    'recent_drafts',
                ],
            ]);
    }

    // ----- Zero state -----

    public function test_zero_state_returns_all_zeros(): void
    {
        $user = $this->createUser();
        $response = $this->actingAs($user)->getJson('/api/v1/education/generated-content');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'draft_count'    => 0,
                    'approved_count' => 0,
                    'rejected_count' => 0,
                ],
            ]);

        $this->assertIsArray($response->json('data.recent_drafts'));
        $this->assertEmpty($response->json('data.recent_drafts'));
    }

    // ----- Draft count -----

    public function test_draft_count_reflects_auto_generated_drafts(): void
    {
        $this->seedDrafts(siteId: null, count: 3, status: EducationArticle::STATUS_DRAFT);
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/education/generated-content');

        $response->assertStatus(200);
        $this->assertSame(3, $response->json('data.draft_count'));
    }

    // ----- Approved count -----

    public function test_approved_count_reflects_reviewed_published_articles(): void
    {
        $this->seedDrafts(siteId: null, count: 2, status: EducationArticle::STATUS_PUBLISHED, reviewed: true);
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/education/generated-content');

        $response->assertStatus(200);
        $this->assertSame(2, $response->json('data.approved_count'));
    }

    public function test_published_without_review_not_counted_as_approved(): void
    {
        $this->seedDrafts(siteId: null, count: 1, status: EducationArticle::STATUS_PUBLISHED, reviewed: false);
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/education/generated-content');

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('data.approved_count'));
    }

    // ----- Rejected count -----

    public function test_rejected_count_reflects_reviewed_archived_articles(): void
    {
        $this->seedDrafts(siteId: null, count: 1, status: EducationArticle::STATUS_ARCHIVED, reviewed: true);
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/education/generated-content');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('data.rejected_count'));
    }

    // ----- Provider usage -----

    public function test_provider_usage_aggregates_successful_log_entries(): void
    {
        $this->seedGenerationLog(provider: 'claude', count: 3, siteId: null);
        $this->seedGenerationLog(provider: 'openai', count: 1, siteId: null);
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/education/generated-content');

        $response->assertStatus(200);
        $usage = $response->json('data.provider_usage');
        $this->assertSame(3, $usage['claude']);
        $this->assertSame(1, $usage['openai']);
    }

    // ----- Recent drafts -----

    public function test_recent_drafts_capped_at_ten(): void
    {
        $this->seedDrafts(siteId: null, count: 15, status: EducationArticle::STATUS_DRAFT);
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/education/generated-content');

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data.recent_drafts'));
    }

    public function test_recent_drafts_include_expected_fields(): void
    {
        $this->seedDrafts(siteId: null, count: 1, status: EducationArticle::STATUS_DRAFT);
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/education/generated-content');

        $draft = $response->json('data.recent_drafts.0');
        $this->assertArrayHasKey('id',           $draft);
        $this->assertArrayHasKey('title',        $draft);
        $this->assertArrayHasKey('module_slug',  $draft);
        $this->assertArrayHasKey('content_type', $draft);
        $this->assertArrayHasKey('created_at',   $draft);
    }

    public function test_recent_drafts_does_not_include_non_auto_generated_articles(): void
    {
        // Regular (manually created) published article
        EducationArticle::create([
            'site_id'        => null,
            'author_id'      => 1,
            'title'          => 'Manual Article',
            'slug'           => 'manual-article',
            'body'           => str_repeat('content ', 20),
            'status'         => EducationArticle::STATUS_DRAFT,
            'auto_generated' => false,
            'module_slug'    => 'invoices',
            'content_type'   => 'articles',
        ]);

        $user = $this->createUser();
        $response = $this->actingAs($user)->getJson('/api/v1/education/generated-content');

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('data.draft_count'));
        $this->assertEmpty($response->json('data.recent_drafts'));
    }

    // -----------------------------------------------------------------------

    private function createUser(): object
    {
        return new class {
            public int $id = 1;
            public string $role = 'admin';
        };
    }

    private function seedDrafts(?int $siteId, int $count, string $status, bool $reviewed = false): void
    {
        for ($i = 0; $i < $count; $i++) {
            EducationArticle::create([
                'site_id'         => $siteId,
                'author_id'       => 1,
                'title'           => "Auto Article {$i}",
                'slug'            => "auto-article-{$i}-" . uniqid(),
                'body'            => str_repeat('body content ', 10),
                'status'          => $status,
                'auto_generated'  => true,
                'module_slug'     => 'invoices',
                'content_type'    => 'articles',
                'reviewed_by_id'  => $reviewed ? 1 : null,
                'reviewed_at'     => $reviewed ? now() : null,
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
