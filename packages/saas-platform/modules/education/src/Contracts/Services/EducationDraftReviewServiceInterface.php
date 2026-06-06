<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use SaaSPlatform\Modules\Education\Exceptions\DraftNotReviewableException;
use SaaSPlatform\Modules\Education\Models\EducationArticle;

/**
 * Contract for the EDU-16 human review workflow.
 *
 * Both approve() and reject() are idempotent:
 *   - Calling approve() on an already-published article returns the article unchanged.
 *   - Calling reject() on an already-archived article returns the article unchanged.
 *   - Calling the opposite action on a reviewed article throws DraftNotReviewableException.
 *
 * Tenant isolation is the caller's responsibility (policy gate must run before this service).
 */
interface EducationDraftReviewServiceInterface
{
    /**
     * Return a paginated list of auto-generated articles awaiting review for a site.
     *
     * Returns only 'draft' auto-generated articles (FIFO — oldest first).
     *
     * @param  array<string, mixed> $filters  Optional: module_slug, content_type, search
     */
    public function pendingQueue(?int $siteId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Approve a draft article.
     *
     * Sets status='published', reviewed_by_id, reviewed_at=now().
     * Idempotent on already-published articles (returns unchanged).
     *
     * @throws DraftNotReviewableException  when the article is not reviewable and not already published
     */
    public function approve(EducationArticle $article, int $reviewerId): EducationArticle;

    /**
     * Reject a draft article.
     *
     * Sets status='archived', reviewed_by_id, reviewed_at=now(), review_note.
     * Idempotent on already-archived articles (returns unchanged).
     *
     * @throws DraftNotReviewableException  when the article is not reviewable and not already archived
     * @throws \InvalidArgumentException    when $note is empty
     */
    public function reject(EducationArticle $article, int $reviewerId, string $note): EducationArticle;

    /**
     * Return review throughput analytics for a site.
     *
     * Returns:
     *   pending_count    — drafts awaiting review
     *   approved_count   — approved (published) auto-generated articles
     *   rejected_count   — rejected (archived) auto-generated articles
     *   avg_review_hours — average hours from created_at to reviewed_at (null if no reviews)
     */
    public function reviewAnalytics(?int $siteId): array;
}
