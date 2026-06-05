<?php

namespace SaaSPlatform\Modules\Education\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Education\Contracts\Services\ContentReadinessServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationAnalyticsServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\KnowledgeGapServiceInterface;
use SaaSPlatform\Modules\Education\Http\Resources\EducationAnalyticsResource;

/**
 * GET /api/v1/education/analytics
 *
 * Returns a combined analytics snapshot for the authenticated user:
 *   — profile scores
 *   — recommendation metrics
 *   — knowledge gap summary
 *   — content readiness summary
 *   — aggregated analytics
 */
class EducationAnalyticsController extends Controller
{
    public function __construct(
        private readonly EducationAnalyticsServiceInterface $analytics,
        private readonly KnowledgeGapServiceInterface       $gaps,
        private readonly ContentReadinessServiceInterface   $readiness,
    ) {}

    /**
     * GET /api/v1/education/analytics
     *
     * Returns a combined analytics snapshot for the authenticated user + site + platform.
     */
    public function show(Request $request): JsonResponse
    {
        $user   = auth()->user();
        $siteId = $request->attributes->get('tenant_site')?->id;

        if (! $user) {
            return response()->json(['message' => 'unauthorized'], 401);
        }

        // User-scoped analytics
        $userAnalytics = $this->analytics->userAnalytics($user->id, $siteId);

        // Single detectForUser() call — both gapList and gapCounts derive from it (H4 fix)
        $gapCollection = $this->gaps->detectForUser($user->id, $siteId);
        $gapList       = $gapCollection->take(5)->values();
        $gapCounts     = $gapCollection->groupBy('priority')->map->count();

        // Site-scoped analytics
        $siteAnalytics = $this->analytics->siteAnalytics($siteId);

        // Platform-wide analytics
        $platformAnalytics = $this->analytics->platformAnalytics();

        // Top 5 content deficits (modules with lowest readiness) — reuse from site analytics
        $readinessModules = $this->readiness->evaluate($siteId);
        $topDeficits = $readinessModules
            ->filter(fn($m) => ! empty($m['missing_content']))
            ->take(5)
            ->values();

        $payload = [
            'profile' => $userAnalytics['profile_scores'],

            'recommendations' => array_merge(
                $userAnalytics['recommendations'],
                ['acceptance_rate' => $userAnalytics['recommendation_acceptance_rate']],
            ),

            'gaps' => [
                'critical_count' => (int) ($gapCounts->get(KnowledgeGapServiceInterface::PRIORITY_CRITICAL, 0)),
                'high_count'     => (int) ($gapCounts->get(KnowledgeGapServiceInterface::PRIORITY_HIGH, 0)),
                'medium_count'   => (int) ($gapCounts->get(KnowledgeGapServiceInterface::PRIORITY_MEDIUM, 0)),
                'low_count'      => (int) ($gapCounts->get(KnowledgeGapServiceInterface::PRIORITY_LOW, 0)),
                'top_gaps'       => $gapList,
            ],

            'readiness' => [
                'top_deficits'        => $topDeficits,
                'site_distribution'   => $siteAnalytics['readiness_distribution'],
            ],

            'analytics' => [
                'completion_rate'                => $userAnalytics['completion_rate'],
                'recommendation_acceptance_rate' => $userAnalytics['recommendation_acceptance_rate'],
                'site'                           => $siteAnalytics,
                'platform'                       => $platformAnalytics,
            ],
        ];

        return (new EducationAnalyticsResource($payload))->response();
    }
}
