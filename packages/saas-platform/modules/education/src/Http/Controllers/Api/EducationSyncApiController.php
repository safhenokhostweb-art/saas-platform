<?php

namespace SaaSPlatform\Modules\Education\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Education\Models\EducationArticle;
use SaaSPlatform\Modules\Education\Models\EducationLearningPath;
use SaaSPlatform\Modules\Education\Models\EducationWalkthrough;
use SaaSPlatform\Modules\Education\Services\EducationChangeDetectorService;
use SaaSPlatform\Modules\Education\Services\EducationSyncService;

/**
 * API endpoints consumed by customer-site to pull eligible education content.
 * Rule 12: differential sync via hash comparison.
 * Rule 13: smart recommendations respect permissions and availability.
 */
class EducationSyncApiController extends Controller
{
    public function __construct(
        private readonly EducationSyncService          $syncService,
        private readonly EducationChangeDetectorService $changeDetector,
    ) {}

    /**
     * F-04: Verify the authenticated user is allowed to access the given siteId.
     * Platform admins (is_admin=true) may access all sites.
     * Site-level tokens must carry a matching tenant_site attribute.
     */
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
    // GET /api/v1/education/sync/{siteId}/manifest
    // Returns article slugs → hashes for differential sync.
    // ──────────────────────────────────────────────────────────────────────────

    public function manifest(Request $request, int $siteId): JsonResponse
    {
        $this->authorizeSite($request, $siteId);

        $manifest = $this->changeDetector->buildSyncManifest($siteId);

        return response()->json([
            'site_id'      => $siteId,
            'manifest'     => $manifest,
            'total'        => count($manifest),
            'generated_at' => now()->toISOString(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POST /api/v1/education/sync/{siteId}/diff
    // Body: { manifest: { slug => { documentation_hash } } }
    // Returns: new, updated, removed slugs.
    // ──────────────────────────────────────────────────────────────────────────

    public function diff(Request $request, int $siteId): JsonResponse
    {
        $this->authorizeSite($request, $siteId);

        $clientManifest = $request->input('manifest', []);

        $diff = $this->changeDetector->diffManifests($siteId, $clientManifest);

        return response()->json([
            'site_id' => $siteId,
            'diff'    => $diff,
            'counts'  => [
                'new'     => count($diff['new']),
                'updated' => count($diff['updated']),
                'removed' => count($diff['removed']),
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GET /api/v1/education/sync/{siteId}/articles
    // Optional ?slugs=slug1,slug2 for targeted fetch (differential)
    // ──────────────────────────────────────────────────────────────────────────

    public function articles(Request $request, int $siteId): JsonResponse
    {
        $this->authorizeSite($request, $siteId);

        $eligible        = $this->syncService->discoverEligibleFeatures($siteId);
        $eligibleModules = $eligible->pluck('module_slug')->unique()->values()->all();

        // F-14: Paginated — default 50 per page, max 200
        $perPage = min((int) $request->input('per_page', 50), 200);

        $query = EducationArticle::where('site_id', $siteId)
            ->whereIn('module_slug', $eligibleModules)
            ->where('status', EducationArticle::STATUS_PUBLISHED)
            ->orderByDesc('updated_at');

        if ($request->has('slugs')) {
            $slugList = array_filter(explode(',', $request->input('slugs', '')));
            $query->whereIn('slug', $slugList);
        }

        $paginator = $query->paginate($perPage);
        $articles  = collect($paginator->items())->map(fn(EducationArticle $a) => $this->articlePayload($a));

        return response()->json([
            'site_id'      => $siteId,
            'articles'     => $articles,
            'total'        => $paginator->total(),
            'per_page'     => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'generated_at' => now()->toISOString(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GET /api/v1/education/sync/{siteId}/walkthroughs
    // ──────────────────────────────────────────────────────────────────────────

    public function walkthroughs(Request $request, int $siteId): JsonResponse
    {
        $this->authorizeSite($request, $siteId);

        $eligible        = $this->syncService->discoverEligibleFeatures($siteId);
        $eligibleModules = $eligible->pluck('module_slug')->unique()->values()->all();

        // F-14: Paginated — default 50 per page, max 200
        $perPage = min((int) $request->input('per_page', 50), 200);

        $paginator    = EducationWalkthrough::where('site_id', $siteId)
            ->whereIn('module_slug', $eligibleModules)
            ->where('is_active', true)
            ->with('steps')
            ->paginate($perPage);

        $walkthroughs = collect($paginator->items())->map(fn(EducationWalkthrough $w) => [
                'id'                    => $w->id,
                'title'                 => $w->title,
                'slug'                  => $w->slug,
                'trigger_route'         => $w->trigger_route,
                'module_slug'           => $w->module_slug,
                'feature_key'           => $w->feature_key,
                'documentation_hash'    => $w->documentation_hash,
                'documentation_version' => $w->documentation_version ?? 1,
                'updated_at'            => $w->updated_at?->toISOString(),
                'steps'                 => $w->steps->map(fn($s) => [
                    'step_number'     => $s->step_number,
                    'title'           => $s->title,
                    'body'            => $s->body,
                    'target_selector' => $s->target_selector,
                    'action'          => $s->action,
                ]),
            ]);

        return response()->json([
            'site_id'      => $siteId,
            'walkthroughs' => $walkthroughs,
            'total'        => $paginator->total(),
            'per_page'     => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GET /api/v1/education/sync/{siteId}/learning-paths
    // ──────────────────────────────────────────────────────────────────────────

    public function learningPaths(Request $request, int $siteId): JsonResponse
    {
        $this->authorizeSite($request, $siteId);

        // F-14: Paginated — default 50 per page, max 200
        $perPage   = min((int) $request->input('per_page', 50), 200);

        $paginator = EducationLearningPath::where('site_id', $siteId)
            ->where('is_active', true)
            ->with('steps')
            ->paginate($perPage);

        $paths = collect($paginator->items())->map(fn(EducationLearningPath $p) => [
                'id'                    => $p->id,
                'title'                 => $p->title,
                'slug'                  => $p->slug,
                'description'           => $p->description,
                'path_type'             => $p->path_type,
                'target_roles'          => $p->target_roles,
                'estimated_minutes'     => $p->estimated_minutes,
                'documentation_hash'    => $p->documentation_hash,
                'documentation_version' => $p->documentation_version ?? 1,
                'updated_at'            => $p->updated_at?->toISOString(),
                'steps'                 => $p->steps->map(fn($s) => [
                    'step_number'   => $s->step_number,
                    'title'         => $s->title,
                    'step_type'     => $s->step_type,
                    'reference_id'  => $s->reference_id,
                    'is_required'   => $s->is_required,
                ]),
            ]);

        return response()->json([
            'site_id'        => $siteId,
            'learning_paths' => $paths,
            'total'          => $paginator->total(),
            'per_page'       => $paginator->perPage(),
            'current_page'   => $paginator->currentPage(),
            'last_page'      => $paginator->lastPage(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // POST /api/v1/education/sync/{siteId}/trigger
    // Trigger a full sync for the site (admin/platform use).
    // ──────────────────────────────────────────────────────────────────────────

    public function trigger(Request $request, int $siteId): JsonResponse
    {
        $this->authorizeSite($request, $siteId);

        $differential = (bool) $request->input('differential', true);
        $result = $this->syncService->syncForSite($siteId, $differential);

        return response()->json($result);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private
    // ──────────────────────────────────────────────────────────────────────────

    private function articlePayload(EducationArticle $article): array
    {
        return [
            'id'                    => $article->id,
            'title'                 => $article->title,
            'slug'                  => $article->slug,
            'excerpt'               => $article->excerpt,
            'body'                  => $article->body,
            'module_slug'           => $article->module_slug,
            'route_pattern'         => $article->route_pattern,
            'feature_key'           => $article->feature_key,
            'target_roles'          => $article->target_roles,
            'content_type'          => $article->content_type,
            'documentation_version' => $article->documentation_version ?? 1,
            'documentation_hash'    => $article->documentation_hash,
            'last_updated_label'    => $article->last_updated_label,
            'last_generated_at'     => $article->last_generated_at?->toISOString(),
            'updated_at'            => $article->updated_at?->toISOString(),
        ];
    }
}
