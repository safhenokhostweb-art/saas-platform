<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Providers\EducationServiceProvider;

/**
 * EDU-16 — HTTP tests for approve/reject endpoints.
 *
 * Covers: authentication, admin gate, approve flow, reject flow,
 * validation, idempotency, and site-level isolation.
 */
class ReviewEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [EducationServiceProvider::class];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeAdmin(int $id = 1, ?int $siteId = null): object
    {
        return new class($id, $siteId) {
            public bool $is_admin = true;
            public function __construct(public int $id, public ?int $site_id) {}
        };
    }

    private function makeNonAdmin(): object
    {
        return new class {
            public int $id = 99;
            public bool $is_admin = false;
            public ?int $site_id = null;
        };
    }

    private function makeDraft(array $overrides = []): EducationArticle
    {
        return EducationArticle::create(array_merge([
            'site_id'        => null,
            'author_id'      => 1,
            'title'          => 'AI Draft ' . uniqid(),
            'slug'           => 'ai-draft-' . uniqid(),
            'body'           => str_repeat('content ', 20),
            'status'         => EducationArticle::STATUS_DRAFT,
            'auto_generated' => true,
            'module_slug'    => 'invoices',
            'content_type'   => 'articles',
        ], $overrides));
    }

    // ── Authentication ────────────────────────────────────────────────────────

    public function test_approve_requires_authentication(): void
    {
        $article = $this->makeDraft();
        $this->postJson("/api/v1/education/generated-content/{$article->id}/approve")
            ->assertStatus(401);
    }

    public function test_reject_requires_authentication(): void
    {
        $article = $this->makeDraft();
        $this->postJson("/api/v1/education/generated-content/{$article->id}/reject", ['review_note' => 'some reason'])
            ->assertStatus(401);
    }

    // ── Authorization (admin gate) ────────────────────────────────────────────

    public function test_approve_returns_403_for_non_admin(): void
    {
        $article = $this->makeDraft();
        $this->actingAs($this->makeNonAdmin())
            ->postJson("/api/v1/education/generated-content/{$article->id}/approve")
            ->assertStatus(403);
    }

    public function test_reject_returns_403_for_non_admin(): void
    {
        $article = $this->makeDraft();
        $this->actingAs($this->makeNonAdmin())
            ->postJson("/api/v1/education/generated-content/{$article->id}/reject", ['review_note' => 'some reason'])
            ->assertStatus(403);
    }

    // ── Approve flow ─────────────────────────────────────────────────────────

    public function test_approve_publishes_draft_article(): void
    {
        $article = $this->makeDraft();
        $admin   = $this->makeAdmin();

        $this->actingAs($admin)
            ->postJson("/api/v1/education/generated-content/{$article->id}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', EducationArticle::STATUS_PUBLISHED);

        $this->assertDatabaseHas('edu_articles', [
            'id'             => $article->id,
            'status'         => EducationArticle::STATUS_PUBLISHED,
            'reviewed_by_id' => $admin->id,
        ]);
    }

    public function test_approve_is_idempotent(): void
    {
        $article = $this->makeDraft([
            'status'         => EducationArticle::STATUS_PUBLISHED,
            'reviewed_by_id' => 1,
            'reviewed_at'    => now(),
        ]);
        $admin = $this->makeAdmin(id: 99);

        $this->actingAs($admin)
            ->postJson("/api/v1/education/generated-content/{$article->id}/approve")
            ->assertStatus(200);

        // reviewed_by_id must remain unchanged (original reviewer)
        $this->assertDatabaseHas('edu_articles', ['id' => $article->id, 'reviewed_by_id' => 1]);
    }

    public function test_approve_returns_422_for_non_draft_article(): void
    {
        // Archived article cannot be approved
        $article = $this->makeDraft(['status' => EducationArticle::STATUS_ARCHIVED]);

        $this->actingAs($this->makeAdmin())
            ->postJson("/api/v1/education/generated-content/{$article->id}/approve")
            ->assertStatus(422);
    }

    public function test_approve_returns_422_for_non_auto_generated_article(): void
    {
        $article = $this->makeDraft(['auto_generated' => false]);

        $this->actingAs($this->makeAdmin())
            ->postJson("/api/v1/education/generated-content/{$article->id}/approve")
            ->assertStatus(422);
    }

    public function test_approve_returns_404_for_unknown_article(): void
    {
        $this->actingAs($this->makeAdmin())
            ->postJson('/api/v1/education/generated-content/99999/approve')
            ->assertStatus(404);
    }

    // ── Reject flow ──────────────────────────────────────────────────────────

    public function test_reject_archives_draft_with_note(): void
    {
        $article = $this->makeDraft();
        $admin   = $this->makeAdmin();

        $this->actingAs($admin)
            ->postJson("/api/v1/education/generated-content/{$article->id}/reject", [
                'review_note' => 'محتوا دقیق نیست',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', EducationArticle::STATUS_ARCHIVED)
            ->assertJsonPath('data.review_note', 'محتوا دقیق نیست');

        $this->assertDatabaseHas('edu_articles', [
            'id'             => $article->id,
            'status'         => EducationArticle::STATUS_ARCHIVED,
            'reviewed_by_id' => $admin->id,
            'review_note'    => 'محتوا دقیق نیست',
        ]);
    }

    public function test_reject_requires_review_note(): void
    {
        $article = $this->makeDraft();

        $this->actingAs($this->makeAdmin())
            ->postJson("/api/v1/education/generated-content/{$article->id}/reject")
            ->assertStatus(422);
    }

    public function test_reject_requires_review_note_min_5_chars(): void
    {
        $article = $this->makeDraft();

        $this->actingAs($this->makeAdmin())
            ->postJson("/api/v1/education/generated-content/{$article->id}/reject", [
                'review_note' => 'bad',
            ])
            ->assertStatus(422);
    }

    public function test_reject_is_idempotent(): void
    {
        $article = $this->makeDraft([
            'status'         => EducationArticle::STATUS_ARCHIVED,
            'reviewed_by_id' => 2,
            'reviewed_at'    => now(),
            'review_note'    => 'original reason',
        ]);

        $this->actingAs($this->makeAdmin(id: 99))
            ->postJson("/api/v1/education/generated-content/{$article->id}/reject", [
                'review_note' => 'new reason here',
            ])
            ->assertStatus(200);

        // note must remain unchanged
        $this->assertDatabaseHas('edu_articles', ['id' => $article->id, 'review_note' => 'original reason']);
    }

    // ── Site isolation ────────────────────────────────────────────────────────

    public function test_site_admin_cannot_approve_other_sites_draft(): void
    {
        // Article belongs to site 2
        $article   = $this->makeDraft(['site_id' => 2]);
        // Admin belongs to site 1
        $siteAdmin = $this->makeAdmin(id: 10, siteId: 1);

        $this->actingAs($siteAdmin)
            ->postJson("/api/v1/education/generated-content/{$article->id}/approve")
            ->assertStatus(403);
    }

    public function test_platform_admin_can_approve_any_sites_draft(): void
    {
        $article       = $this->makeDraft(['site_id' => 5]);
        $platformAdmin = $this->makeAdmin(id: 1, siteId: null); // no site_id = platform admin

        $this->actingAs($platformAdmin)
            ->postJson("/api/v1/education/generated-content/{$article->id}/approve")
            ->assertStatus(200);
    }

    public function test_site_admin_can_approve_own_sites_draft(): void
    {
        $article   = $this->makeDraft(['site_id' => 3]);
        $siteAdmin = $this->makeAdmin(id: 10, siteId: 3);

        $this->actingAs($siteAdmin)
            ->postJson("/api/v1/education/generated-content/{$article->id}/approve")
            ->assertStatus(200);
    }
}
