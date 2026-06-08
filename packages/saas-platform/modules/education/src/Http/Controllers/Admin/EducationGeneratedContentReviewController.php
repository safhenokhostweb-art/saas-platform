<?php

namespace SaaSPlatform\Modules\Education\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationDraftReviewServiceInterface;
use SaaSPlatform\Modules\Education\Exceptions\DraftNotReviewableException;
use SaaSPlatform\Modules\Education\Models\EducationArticle;

class EducationGeneratedContentReviewController extends Controller
{
    public function __construct(
        private readonly EducationDraftReviewServiceInterface $reviewService,
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('viewGeneratedContent', EducationArticle::class);

        $siteId  = $request->attributes->get('tenant_site')?->id;
        $filters = $request->only(['module_slug', 'content_type', 'search']);

        $pending   = $this->reviewService->pendingQueue($siteId, $filters, 20);
        $analytics = $this->reviewService->reviewAnalytics($siteId);

        return view('education::admin.education.generated-content.review', compact('pending', 'analytics'));
    }

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
            'message' => 'مقاله تأیید و منتشر شد.',
            'data'    => ['id' => $article->id, 'status' => $article->status],
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
            'message' => 'مقاله رد و بایگانی شد.',
            'data'    => ['id' => $article->id, 'status' => $article->status],
        ]);
    }

    public function bulkApprove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer'],
        ]);

        Gate::authorize('viewGeneratedContent', EducationArticle::class);

        $approved = 0;
        $skipped  = 0;

        foreach ($validated['ids'] as $id) {
            $article = EducationArticle::find($id);
            if (! $article) {
                $skipped++;
                continue;
            }
            try {
                $this->reviewService->approve($article, auth()->id());
                $approved++;
            } catch (DraftNotReviewableException) {
                $skipped++;
            }
        }

        return response()->json([
            'message'  => "{$approved} مقاله تأیید شد" . ($skipped ? "، {$skipped} مورد نادیده گرفته شد" : '') . '.',
            'approved' => $approved,
            'skipped'  => $skipped,
        ]);
    }
}
