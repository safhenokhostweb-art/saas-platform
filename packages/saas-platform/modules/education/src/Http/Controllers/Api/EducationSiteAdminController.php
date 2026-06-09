<?php

namespace SaaSPlatform\Modules\Education\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationLearningPath;
use SaaSPlatform\Modules\Education\Models\EducationLearningPathStep;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;
use SaaSPlatform\Modules\Education\Models\EducationWalkthroughStep;

/**
 * CRUD API for customer-site admins to manage their own education content.
 * All operations are scoped to the requesting site's site_id.
 * Auth: auth:sanctum + site authorization (same as EducationSyncApiController).
 */
class EducationSiteAdminController extends Controller
{
    private function authorizeSite(Request $request, int $siteId): void
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->is_admin) {
            return;
        }

        $tenantSiteId = $request->attributes->get('tenant_site')?->id;

        if ((int) $tenantSiteId !== $siteId) {
            abort(403, 'Access denied for this site.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ARTICLES
    // ──────────────────────────────────────────────────────────────────────────

    public function articleIndex(Request $request, int $siteId): JsonResponse
    {
        $this->authorizeSite($request, $siteId);

        $query = EducationArticle::where('site_id', $siteId)
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(fn($s) => $s->where('title', 'like', "%{$q}%")
                ->orWhere('excerpt', 'like', "%{$q}%"));
        }

        $paginator = $query->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'articles'     => $paginator->items(),
            'total'        => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
        ]);
    }

    public function articleStore(Request $request, int $siteId): JsonResponse
    {
        $this->authorizeSite($request, $siteId);

        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'body'         => 'required|string',
            'excerpt'      => 'nullable|string|max:500',
            'module_slug'  => 'required|string|max:100',
            'content_type' => 'nullable|in:article,guide,tutorial,faq',
            'status'       => 'nullable|in:draft,published',
            'target_roles' => 'nullable|array',
            'feature_key'  => 'nullable|string|max:100',
            'route_pattern'=> 'nullable|string|max:200',
            'tags'         => 'nullable|array',
        ]);

        $article = EducationArticle::create([
            'site_id'        => $siteId,
            'author_id'      => $request->user()->id,
            'title'          => $data['title'],
            'slug'           => Str::slug($data['title']) . '-' . Str::random(4),
            'body'           => $data['body'],
            'excerpt'        => $data['excerpt'] ?? Str::limit(strip_tags($data['body']), 180),
            'module_slug'    => $data['module_slug'],
            'content_type'   => $data['content_type'] ?? 'article',
            'status'         => $data['status'] ?? EducationArticle::STATUS_DRAFT,
            'target_roles'   => $data['target_roles'] ?? [],
            'feature_key'    => $data['feature_key'] ?? null,
            'route_pattern'  => $data['route_pattern'] ?? null,
            'tags'           => $data['tags'] ?? [],
            'auto_generated' => false,
            'documentation_version' => 1,
            'documentation_hash'    => EducationArticle::computeHashFromParts(
                $data['title'], $data['body'], $data['excerpt'] ?? ''
            ),
        ]);

        return response()->json(['article' => $article], 201);
    }

    public function articleUpdate(Request $request, int $siteId, int $articleId): JsonResponse
    {
        $this->authorizeSite($request, $siteId);

        $article = EducationArticle::where('site_id', $siteId)->findOrFail($articleId);

        $data = $request->validate([
            'title'        => 'sometimes|required|string|max:255',
            'body'         => 'sometimes|required|string',
            'excerpt'      => 'nullable|string|max:500',
            'module_slug'  => 'sometimes|required|string|max:100',
            'content_type' => 'nullable|in:article,guide,tutorial,faq',
            'status'       => 'nullable|in:draft,published,archived',
            'target_roles' => 'nullable|array',
            'feature_key'  => 'nullable|string|max:100',
            'route_pattern'=> 'nullable|string|max:200',
            'tags'         => 'nullable|array',
        ]);

        $article->update($data);

        if (isset($data['title']) || isset($data['body']) || isset($data['excerpt'])) {
            $article->documentation_hash = EducationArticle::computeHashFromParts(
                $article->title, $article->body ?? '', $article->excerpt ?? ''
            );
            $article->documentation_version = ($article->documentation_version ?? 1) + 1;
            $article->saveQuietly();
        }

        return response()->json(['article' => $article->fresh()]);
    }

    public function articleDestroy(Request $request, int $siteId, int $articleId): JsonResponse
    {
        $this->authorizeSite($request, $siteId);

        $article = EducationArticle::where('site_id', $siteId)->findOrFail($articleId);
        $article->delete();

        return response()->json(['deleted' => true]);
    }

    public function articlePublish(Request $request, int $siteId, int $articleId): JsonResponse
    {
        $this->authorizeSite($request, $siteId);

        $article = EducationArticle::where('site_id', $siteId)->findOrFail($articleId);
        $article->update([
            'status'       => EducationArticle::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        return response()->json(['article' => $article->fresh()]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // WALKTHROUGHS
    // ──────────────────────────────────────────────────────────────────────────

    public function walkthroughIndex(Request $request, int $siteId): JsonResponse
    {
        $this->authorizeSite($request, $siteId);

        $walkthroughs = EducationWalkthrough::where('site_id', $siteId)
            ->with('steps')
            ->orderByDesc('updated_at')
            ->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'walkthroughs' => $walkthroughs->items(),
            'total'        => $walkthroughs->total(),
            'current_page' => $walkthroughs->currentPage(),
            'last_page'    => $walkthroughs->lastPage(),
        ]);
    }

    public function walkthroughStore(Request $request, int $siteId): JsonResponse
    {
        $this->authorizeSite($request, $siteId);

        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'module_slug'      => 'required|string|max:100',
            'trigger_route'    => 'nullable|string|max:200',
            'is_active'        => 'boolean',
            'target_roles'     => 'nullable|array',
            'steps'            => 'nullable|array',
            'steps.*.title'    => 'required_with:steps|string|max:255',
            'steps.*.body'     => 'required_with:steps|string',
            'steps.*.step_number' => 'required_with:steps|integer|min:1',
        ]);

        $walkthrough = EducationWalkthrough::create([
            'site_id'       => $siteId,
            'title'         => $data['title'],
            'slug'          => Str::slug($data['title']) . '-' . Str::random(4),
            'description'   => $data['description'] ?? null,
            'module_slug'   => $data['module_slug'],
            'trigger_route' => $data['trigger_route'] ?? null,
            'is_active'     => $data['is_active'] ?? true,
            'target_roles'  => $data['target_roles'] ?? [],
            'auto_generated'=> false,
            'documentation_version' => 1,
        ]);

        foreach ($data['steps'] ?? [] as $step) {
            $walkthrough->steps()->create([
                'step_number' => $step['step_number'],
                'title'       => $step['title'],
                'body'        => $step['body'],
                'target_selector' => $step['target_selector'] ?? null,
                'action'          => $step['action'] ?? null,
            ]);
        }

        return response()->json(['walkthrough' => $walkthrough->load('steps')], 201);
    }

    public function walkthroughUpdate(Request $request, int $siteId, int $walkthroughId): JsonResponse
    {
        $this->authorizeSite($request, $siteId);

        $walkthrough = EducationWalkthrough::where('site_id', $siteId)->findOrFail($walkthroughId);

        $data = $request->validate([
            'title'         => 'sometimes|required|string|max:255',
            'description'   => 'nullable|string',
            'module_slug'   => 'sometimes|required|string|max:100',
            'trigger_route' => 'nullable|string|max:200',
            'is_active'     => 'boolean',
            'target_roles'  => 'nullable|array',
        ]);

        $walkthrough->update($data);

        return response()->json(['walkthrough' => $walkthrough->fresh()->load('steps')]);
    }

    public function walkthroughDestroy(Request $request, int $siteId, int $walkthroughId): JsonResponse
    {
        $this->authorizeSite($request, $siteId);

        $walkthrough = EducationWalkthrough::where('site_id', $siteId)->findOrFail($walkthroughId);
        $walkthrough->steps()->delete();
        $walkthrough->delete();

        return response()->json(['deleted' => true]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // LEARNING PATHS
    // ──────────────────────────────────────────────────────────────────────────

    public function learningPathIndex(Request $request, int $siteId): JsonResponse
    {
        $this->authorizeSite($request, $siteId);

        $paths = EducationLearningPath::where('site_id', $siteId)
            ->with('steps')
            ->orderByDesc('updated_at')
            ->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'learning_paths' => $paths->items(),
            'total'          => $paths->total(),
            'current_page'   => $paths->currentPage(),
            'last_page'      => $paths->lastPage(),
        ]);
    }

    public function learningPathStore(Request $request, int $siteId): JsonResponse
    {
        $this->authorizeSite($request, $siteId);

        $data = $request->validate([
            'title'             => 'required|string|max:255',
            'description'       => 'nullable|string',
            'path_type'         => 'nullable|in:onboarding,feature,role,custom',
            'target_roles'      => 'nullable|array',
            'estimated_minutes' => 'nullable|integer|min:1',
            'is_active'         => 'boolean',
        ]);

        $path = EducationLearningPath::create([
            'site_id'           => $siteId,
            'title'             => $data['title'],
            'slug'              => Str::slug($data['title']) . '-' . Str::random(4),
            'description'       => $data['description'] ?? null,
            'path_type'         => $data['path_type'] ?? 'custom',
            'target_roles'      => $data['target_roles'] ?? [],
            'estimated_minutes' => $data['estimated_minutes'] ?? null,
            'is_active'         => $data['is_active'] ?? true,
            'documentation_version' => 1,
        ]);

        return response()->json(['learning_path' => $path], 201);
    }

    public function learningPathUpdate(Request $request, int $siteId, int $pathId): JsonResponse
    {
        $this->authorizeSite($request, $siteId);

        $path = EducationLearningPath::where('site_id', $siteId)->findOrFail($pathId);

        $data = $request->validate([
            'title'             => 'sometimes|required|string|max:255',
            'description'       => 'nullable|string',
            'path_type'         => 'nullable|in:onboarding,feature,role,custom',
            'target_roles'      => 'nullable|array',
            'estimated_minutes' => 'nullable|integer|min:1',
            'is_active'         => 'boolean',
        ]);

        $path->update($data);

        return response()->json(['learning_path' => $path->fresh()->load('steps')]);
    }

    public function learningPathDestroy(Request $request, int $siteId, int $pathId): JsonResponse
    {
        $this->authorizeSite($request, $siteId);

        $path = EducationLearningPath::where('site_id', $siteId)->findOrFail($pathId);
        $path->steps()->delete();
        $path->delete();

        return response()->json(['deleted' => true]);
    }
}
