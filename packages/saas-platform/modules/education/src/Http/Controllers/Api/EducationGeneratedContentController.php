<?php

namespace SaaSPlatform\Modules\Education\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationGenerationLog;

/**
 * GET /api/v1/education/generated-content
 *
 * Returns an overview of AI-generated draft content for the authenticated
 * user's tenant site. Scoped strictly to the request's site_id so drafts
 * from other tenants are never exposed.
 *
 * Response shape:
 * {
 *   "data": {
 *     "draft_count":    <int>   — auto_generated drafts awaiting review
 *     "approved_count": <int>   — auto_generated, published, reviewed_at NOT NULL
 *     "rejected_count": <int>   — auto_generated, archived,  reviewed_at NOT NULL
 *     "provider_usage": { provider: count }
 *     "recent_drafts":  [ { id, title, module_slug, content_type, created_at, meta } ]
 *   }
 * }
 *
 * All counts use a single aggregate query per status; recent_drafts adds one
 * SELECT with LIMIT 10. Total: 5 queries.
 */
class EducationGeneratedContentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user   = auth()->user();
        $siteId = $request->attributes->get('tenant_site')?->id;

        if (! $user) {
            return response()->json(['message' => 'unauthorized'], 401);
        }

        // Requires is_admin — see EducationArticlePolicy::viewGeneratedContent().
        // Throws 403 for ordinary authenticated users.
        Gate::authorize('viewGeneratedContent', EducationArticle::class);

        // Query 1 — draft count (auto-generated, not yet reviewed)
        $draftCount = EducationArticle::query()
            ->where('auto_generated', true)
            ->where('status', EducationArticle::STATUS_DRAFT)
            ->when($siteId !== null, fn($q) => $q->where('site_id', $siteId))
            ->when($siteId === null, fn($q) => $q->whereNull('site_id'))
            ->count();

        // Query 2 — approved count (reviewed + published)
        $approvedCount = EducationArticle::query()
            ->where('auto_generated', true)
            ->where('status', EducationArticle::STATUS_PUBLISHED)
            ->whereNotNull('reviewed_at')
            ->when($siteId !== null, fn($q) => $q->where('site_id', $siteId))
            ->when($siteId === null, fn($q) => $q->whereNull('site_id'))
            ->count();

        // Query 3 — rejected count (reviewed + archived)
        $rejectedCount = EducationArticle::query()
            ->where('auto_generated', true)
            ->where('status', EducationArticle::STATUS_ARCHIVED)
            ->whereNotNull('reviewed_at')
            ->when($siteId !== null, fn($q) => $q->where('site_id', $siteId))
            ->when($siteId === null, fn($q) => $q->whereNull('site_id'))
            ->count();

        // Query 4 — provider usage breakdown from generation logs
        $providerRows = EducationGenerationLog::forSite($siteId)
            ->successful()
            ->selectRaw('provider, COUNT(*) as cnt')
            ->groupBy('provider')
            ->get();

        $providerUsage = $providerRows->pluck('cnt', 'provider')->map(fn($v) => (int) $v)->toArray();

        // Query 5 — 10 most recent draft articles
        $recentDrafts = EducationArticle::query()
            ->where('auto_generated', true)
            ->where('status', EducationArticle::STATUS_DRAFT)
            ->when($siteId !== null, fn($q) => $q->where('site_id', $siteId))
            ->when($siteId === null, fn($q) => $q->whereNull('site_id'))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'title', 'module_slug', 'content_type', 'created_at', 'meta']);

        return response()->json([
            'data' => [
                'draft_count'    => $draftCount,
                'approved_count' => $approvedCount,
                'rejected_count' => $rejectedCount,
                'provider_usage' => $providerUsage,
                'recent_drafts'  => $recentDrafts,
            ],
        ]);
    }
}
