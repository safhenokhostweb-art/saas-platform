<?php

namespace SaaSPlatform\Modules\Education\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationDraftReviewServiceInterface;
use SaaSPlatform\Modules\Education\Exceptions\DraftNotReviewableException;
use SaaSPlatform\Modules\Education\Models\EducationArticle;

/**
 * EDU-16 — Human review endpoints.
 *
 * POST /api/v1/education/generated-content/{id}/approve
 * POST /api/v1/education/generated-content/{id}/reject
 *
 * Both endpoints:
 *   - Require auth:sanctum
 *   - Require is_admin (via EducationArticlePolicy)
 *   - Enforce site-level ownership (policy: platform admin = any site, site admin = own site only)
 *   - Return 404 when article not found
 *   - Return 422 when article is not reviewable
 */
class EducationReviewController extends Controller
{
    public function __construct(
        private readonly EducationDraftReviewServiceInterface $reviewService,
    ) {}

    public function approve(Request $request, int $id): JsonResponse
    {
        $article = EducationArticle::findOrFail($id);

        Gate::authorize('approveGeneratedContent', $article);

        try {
            $article = $this->reviewService->approve($article, auth()->id());
        } catch (DraftNotReviewableException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Article approved and published.',
            'data'    => [
                'id'          => $article->id,
                'status'      => $article->status,
                'reviewed_at' => $article->reviewed_at?->toISOString(),
                'published_at'=> $article->published_at?->toISOString(),
            ],
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'review_note' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $article = EducationArticle::findOrFail($id);

        Gate::authorize('rejectGeneratedContent', $article);

        try {
            $article = $this->reviewService->reject($article, auth()->id(), $validated['review_note']);
        } catch (DraftNotReviewableException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Article rejected and archived.',
            'data'    => [
                'id'          => $article->id,
                'status'      => $article->status,
                'reviewed_at' => $article->reviewed_at?->toISOString(),
                'review_note' => $article->review_note,
            ],
        ]);
    }
}
