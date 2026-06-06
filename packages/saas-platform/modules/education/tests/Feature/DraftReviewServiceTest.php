<?php

namespace SaaSPlatform\Modules\Education\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use SaaSPlatform\Modules\Education\Exceptions\DraftNotReviewableException;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Providers\EducationServiceProvider;
use SaaSPlatform\Modules\Education\Services\Education\EducationDraftReviewService;

/**
 * EDU-16 — EducationDraftReviewService unit tests.
 *
 * Covers: approve flow, reject flow, idempotency, note validation,
 * non-reviewable state guard, and reviewAnalytics aggregates.
 */
class DraftReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [EducationServiceProvider::class];
    }

    private function service(): EducationDraftReviewService
    {
        return $this->app->make(EducationDraftReviewService::class);
    }

    private function makeDraft(array $overrides = []): EducationArticle
    {
        return EducationArticle::create(array_merge([
            'site_id'        => null,
            'author_id'      => 1,
            'title'          => 'Draft Article ' . uniqid(),
            'slug'           => 'draft-article-' . uniqid(),
            'body'           => str_repeat('content ', 20),
            'status'         => EducationArticle::STATUS_DRAFT,
            'auto_generated' => true,
            'module_slug'    => 'invoices',
            'content_type'   => 'articles',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // approve()
    // -------------------------------------------------------------------------

    public function test_approve_sets_published_status(): void
    {
        $article = $this->makeDraft();

        $result = $this->service()->approve($article, reviewerId: 42);

        $this->assertSame(EducationArticle::STATUS_PUBLISHED, $result->status);
        $this->assertSame(42, $result->reviewed_by_id);
        $this->assertNotNull($result->reviewed_at);
    }

    public function test_approve_sets_published_at_when_null(): void
    {
        $article = $this->makeDraft(['published_at' => null]);

        $result = $this->service()->approve($article, reviewerId: 1);

        $this->assertNotNull($result->published_at);
    }

    public function test_approve_preserves_existing_published_at(): void
    {
        $existing = now()->subDays(3);
        $article  = $this->makeDraft(['published_at' => $existing]);

        $result = $this->service()->approve($article, reviewerId: 1);

        $this->assertTrue($result->published_at->eq($existing));
    }

    public function test_approve_is_idempotent_on_already_published(): void
    {
        $article = $this->makeDraft([
            'status'         => EducationArticle::STATUS_PUBLISHED,
            'reviewed_by_id' => 7,
            'reviewed_at'    => now()->subHour(),
        ]);

        $result = $this->service()->approve($article, reviewerId: 99);

        // Reviewer must NOT change — idempotent return
        $this->assertSame(7, $result->reviewed_by_id);
        $this->assertSame(EducationArticle::STATUS_PUBLISHED, $result->status);
    }

    public function test_approve_throws_on_non_auto_generated_draft(): void
    {
        $article = $this->makeDraft(['auto_generated' => false]);

        $this->expectException(DraftNotReviewableException::class);
        $this->service()->approve($article, reviewerId: 1);
    }

    public function test_approve_throws_when_status_is_archived(): void
    {
        $article = $this->makeDraft(['status' => EducationArticle::STATUS_ARCHIVED]);

        $this->expectException(DraftNotReviewableException::class);
        $this->service()->approve($article, reviewerId: 1);
    }

    // -------------------------------------------------------------------------
    // reject()
    // -------------------------------------------------------------------------

    public function test_reject_sets_archived_status(): void
    {
        $article = $this->makeDraft();

        $result = $this->service()->reject($article, reviewerId: 5, note: 'محتوا نادرست است');

        $this->assertSame(EducationArticle::STATUS_ARCHIVED, $result->status);
        $this->assertSame(5, $result->reviewed_by_id);
        $this->assertNotNull($result->reviewed_at);
        $this->assertSame('محتوا نادرست است', $result->review_note);
    }

    public function test_reject_throws_on_empty_note(): void
    {
        $article = $this->makeDraft();

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->reject($article, reviewerId: 1, note: '   ');
    }

    public function test_reject_is_idempotent_on_already_archived(): void
    {
        $article = $this->makeDraft([
            'status'         => EducationArticle::STATUS_ARCHIVED,
            'reviewed_by_id' => 3,
            'reviewed_at'    => now()->subHour(),
            'review_note'    => 'original note',
        ]);

        $result = $this->service()->reject($article, reviewerId: 99, note: 'new note');

        // Reviewer must NOT change — idempotent return
        $this->assertSame(3, $result->reviewed_by_id);
        $this->assertSame('original note', $result->review_note);
    }

    public function test_reject_throws_on_non_auto_generated_draft(): void
    {
        $article = $this->makeDraft(['auto_generated' => false]);

        $this->expectException(DraftNotReviewableException::class);
        $this->service()->reject($article, reviewerId: 1, note: 'some note here');
    }

    // -------------------------------------------------------------------------
    // reviewAnalytics()
    // -------------------------------------------------------------------------

    public function test_review_analytics_counts_correctly(): void
    {
        // 3 pending drafts
        $this->makeDraft();
        $this->makeDraft();
        $this->makeDraft();

        // 2 approved
        $this->makeDraft(['status' => EducationArticle::STATUS_PUBLISHED, 'reviewed_by_id' => 1, 'reviewed_at' => now()]);
        $this->makeDraft(['status' => EducationArticle::STATUS_PUBLISHED, 'reviewed_by_id' => 1, 'reviewed_at' => now()]);

        // 1 rejected
        $this->makeDraft(['status' => EducationArticle::STATUS_ARCHIVED, 'reviewed_by_id' => 1, 'reviewed_at' => now(), 'review_note' => 'bad']);

        $analytics = $this->service()->reviewAnalytics(null);

        $this->assertSame(3, $analytics['pending_count']);
        $this->assertSame(2, $analytics['approved_count']);
        $this->assertSame(1, $analytics['rejected_count']);
    }

    public function test_review_analytics_avg_hours_null_when_no_reviews(): void
    {
        $this->makeDraft();

        $analytics = $this->service()->reviewAnalytics(null);

        $this->assertNull($analytics['avg_review_hours']);
    }

    public function test_review_analytics_respects_site_isolation(): void
    {
        $this->makeDraft(['site_id' => 1]);
        $this->makeDraft(['site_id' => 2]);
        $this->makeDraft(['site_id' => null]);

        $analytics = $this->service()->reviewAnalytics(1);

        $this->assertSame(1, $analytics['pending_count']);
    }

    // -------------------------------------------------------------------------
    // pendingQueue()
    // -------------------------------------------------------------------------

    public function test_pending_queue_returns_only_auto_generated_drafts(): void
    {
        $this->makeDraft();
        $this->makeDraft();
        // Human draft — must not appear
        $this->makeDraft(['auto_generated' => false]);
        // Published — must not appear
        $this->makeDraft(['status' => EducationArticle::STATUS_PUBLISHED]);

        $paginator = $this->service()->pendingQueue(null);

        $this->assertSame(2, $paginator->total());
    }

    public function test_pending_queue_filters_by_module_slug(): void
    {
        $this->makeDraft(['module_slug' => 'invoices']);
        $this->makeDraft(['module_slug' => 'payments']);

        $paginator = $this->service()->pendingQueue(null, ['module_slug' => 'invoices']);

        $this->assertSame(1, $paginator->total());
        $this->assertSame('invoices', $paginator->items()[0]->module_slug);
    }
}
