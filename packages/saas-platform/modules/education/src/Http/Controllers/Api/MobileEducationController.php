<?php

namespace SaaSPlatform\Modules\Education\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationArticleRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationLearningPathRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationSearchServiceInterface;
use SaaSPlatform\Modules\Education\Dto\EducationSearchQuery;
use SaaSPlatform\Modules\Education\Services\EducationArticleService;
use SaaSPlatform\Modules\Education\Services\EducationProgressService;

class MobileEducationController extends Controller
{
    public function __construct(
        private readonly EducationArticleService                  $articles,
        private readonly EducationSearchServiceInterface          $search,
        private readonly EducationLearningPathRepositoryInterface $paths,
        private readonly EducationProgressService                 $progress,
    ) {}

    public function articles(Request $request): JsonResponse
    {
        $siteId = $request->attributes->get('tenant_site')?->id;
        $role   = auth()->user()?->role;

        $items = $this->articles->paginate([
            'site_id'      => $siteId,
            'role'         => $role,
            'status'       => 'published',
            'content_type' => $request->query('content_type'),
            'module_slug'  => $request->query('module_slug'),
        ], 20);

        return response()->json([
            'data'  => $items->items(),
            'meta'  => [
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
                'total'        => $items->total(),
            ],
        ]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $siteId  = $request->attributes->get('tenant_site')?->id;
        $article = $this->articles->find($id);

        if (! $article || $article->status !== 'published') {
            return response()->json(['message' => 'not found'], 404);
        }

        // F-03: Enforce cross-tenant isolation — article must belong to this site or be global
        if ($article->site_id !== null && $article->site_id !== $siteId) {
            return response()->json(['message' => 'not found'], 404);
        }

        $article->incrementView();

        return response()->json(['data' => $article->load('category', 'media')]);
    }

    public function search(Request $request): JsonResponse
    {
        $siteId = $request->attributes->get('tenant_site')?->id;

        $query = EducationSearchQuery::fromArray([
            'term'       => $request->query('q', ''),
            'site_id'    => $siteId,
            'role'       => auth()->user()?->role,
        ]);

        $results = $this->search->search($query);

        return response()->json(['data' => $results]);
    }

    public function learningPaths(Request $request): JsonResponse
    {
        $siteId = $request->attributes->get('tenant_site')?->id;
        $role   = auth()->user()?->role ?? '';

        $items = $this->paths->forRole($role, $siteId);

        return response()->json(['data' => $items]);
    }

    public function markProgress(Request $request, int $articleId): JsonResponse
    {
        $user   = auth()->user();
        $siteId = $request->attributes->get('tenant_site')?->id;

        if (! $user || ! $siteId) {
            return response()->json(['message' => 'unauthorized'], 401);
        }

        $article = $this->articles->find($articleId);
        if (! $article) {
            return response()->json(['message' => 'not found'], 404);
        }

        $progressRecord = $this->progress->markArticleViewed($user->id, $siteId, $article);

        return response()->json(['data' => $progressRecord]);
    }
}
