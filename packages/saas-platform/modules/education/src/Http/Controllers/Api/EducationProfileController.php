<?php

namespace SaaSPlatform\Modules\Education\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Education\Contracts\Services\AIRecommendationScoreServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\KnowledgeGapServiceInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\UserLearningProfileServiceInterface;
use SaaSPlatform\Modules\Education\Http\Resources\LearningProfileResource;
use SaaSPlatform\Modules\Education\Http\Resources\RecommendationResource;
use SaaSPlatform\Modules\Education\Models\EducationRecommendation;

class EducationProfileController extends Controller
{
    public function __construct(
        private readonly UserLearningProfileServiceInterface $profiles,
        private readonly KnowledgeGapServiceInterface        $gaps,
        private readonly AIRecommendationScoreServiceInterface $scorer,
    ) {}

    /**
     * GET /api/v1/education/profile
     *
     * Returns the authenticated user's learning profile, their top knowledge gaps,
     * and AI-scored recommendations — all in one response.
     */
    public function show(Request $request): JsonResponse
    {
        $user   = auth()->user();
        $siteId = $request->attributes->get('tenant_site')?->id;

        if (! $user) {
            return response()->json(['message' => 'unauthorized'], 401);
        }

        $profile = $this->profiles->buildProfile($user->id, $siteId);
        $gaps    = $this->gaps->detectForUser($user->id, $siteId)->take(5);

        // Fetch active recommendations and score them
        $candidates = EducationRecommendation::forUser($user->id)
            ->forSite($siteId)
            ->active()
            ->with(['article', 'walkthrough', 'learningPath'])
            ->orderBy('priority')
            ->limit(20)
            ->get();

        $scoredRecs = $this->scorer->score($candidates, $profile, $gaps)->take(10);

        return response()->json([
            'data' => [
                'profile'         => new LearningProfileResource($profile),
                'knowledge_gaps'  => $gaps->values(),
                'recommendations' => RecommendationResource::collection($scoredRecs),
            ],
        ]);
    }
}
