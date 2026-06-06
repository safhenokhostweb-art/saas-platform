<?php

namespace SaaSPlatform\Modules\Education\Services\Education;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationDraftReviewServiceInterface;
use SaaSPlatform\Modules\Education\Exceptions\DraftNotReviewableException;
use SaaSPlatform\Modules\Education\Models\EducationArticle;

/**
 * Implements the EDU-16 human review workflow.
 *
 * State-machine enforcement:
 *   REVIEWABLE (draft, auto_generated=true) ──▶ approve() ──▶ published
 *                                           └──▶ reject()  ──▶ archived
 *
 * Idempotency:
 *   - approve() on a published article: returns as-is (no-op)
 *   - reject()  on an archived article: returns as-is (no-op)
 *   - Opposite action on a reviewed article: throws DraftNotReviewableException
 *
 * Tenant isolation is enforced at the controller/policy layer.
 * This service operates on the article it receives and trusts the caller has
 * already verified site ownership.
 *
 * Performance: approve() and reject() each execute 1 SQL UPDATE inside a
 * transaction. reviewAnalytics() runs 2 aggregate queries. No N+1.
 */
class EducationDraftReviewService implements EducationDraftReviewServiceInterface
{
    public function pendingQueue(?int $siteId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = EducationArticle::query()
            ->pendingReview()
            ->when($siteId !== null, fn($q) => $q->where('site_id', $siteId))
            ->when($siteId === null, fn($q) => $q->whereNull('site_id'));

        if (! empty($filters['module_slug'])) {
            $query->where('module_slug', $filters['module_slug']);
        }

        if (! empty($filters['content_type'])) {
            $query->where('content_type', $filters['content_type']);
        }

        if (! empty($filters['search'])) {
            $query->where('title', 'like', '%' . $filters['search'] . '%');
        }

        // FIFO: oldest unreviewed drafts shown first
        return $query
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);
    }

    public function approve(EducationArticle $article, int $reviewerId): EducationArticle
    {
        // Idempotent: already published — return unchanged
        if ($article->status === EducationArticle::STATUS_PUBLISHED) {
            return $article;
        }

        if (! $article->isReviewable()) {
            throw new DraftNotReviewableException($article);
        }

        return DB::transaction(function () use ($article, $reviewerId) {
            $article->update([
                'status'         => EducationArticle::STATUS_PUBLISHED,
                'reviewed_by_id' => $reviewerId,
                'reviewed_at'    => now(),
                'published_at'   => $article->published_at ?? now(),
            ]);

            return $article->fresh();
        });
    }

    public function reject(EducationArticle $article, int $reviewerId, string $note): EducationArticle
    {
        if (empty(trim($note))) {
            throw new \InvalidArgumentException('A rejection note is required.');
        }

        // Idempotent: already archived — return unchanged
        if ($article->status === EducationArticle::STATUS_ARCHIVED) {
            return $article;
        }

        if (! $article->isReviewable()) {
            throw new DraftNotReviewableException($article);
        }

        return DB::transaction(function () use ($article, $reviewerId, $note) {
            $article->update([
                'status'         => EducationArticle::STATUS_ARCHIVED,
                'reviewed_by_id' => $reviewerId,
                'reviewed_at'    => now(),
                'review_note'    => $note,
            ]);

            return $article->fresh();
        });
    }

    public function reviewAnalytics(?int $siteId): array
    {
        $scope = fn($q) => $q
            ->where('auto_generated', true)
            ->when($siteId !== null, fn($q) => $q->where('site_id', $siteId))
            ->when($siteId === null, fn($q) => $q->whereNull('site_id'));

        // Query 1 — pending count (auto-generated drafts awaiting review)
        $pendingCount = EducationArticle::query()
            ->tap($scope)
            ->where('status', EducationArticle::STATUS_DRAFT)
            ->count();

        // Query 2 — reviewed aggregates + average review time
        $reviewedRow = EducationArticle::query()
            ->tap($scope)
            ->whereNotNull('reviewed_at')
            ->selectRaw("
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected_count,
                AVG(TIMESTAMPDIFF(SECOND, created_at, reviewed_at)) / 3600.0 as avg_review_hours
            ", [EducationArticle::STATUS_PUBLISHED, EducationArticle::STATUS_ARCHIVED])
            ->first();

        return [
            'pending_count'    => $pendingCount,
            'approved_count'   => (int) ($reviewedRow->approved_count ?? 0),
            'rejected_count'   => (int) ($reviewedRow->rejected_count ?? 0),
            'avg_review_hours' => $reviewedRow->avg_review_hours !== null
                ? round((float) $reviewedRow->avg_review_hours, 2)
                : null,
        ];
    }
}
