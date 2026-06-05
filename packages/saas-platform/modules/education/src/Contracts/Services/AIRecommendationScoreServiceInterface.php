<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Dto\UserLearningProfileDto;

interface AIRecommendationScoreServiceInterface
{
    /**
     * Score an existing collection of recommendation candidates (0–100 each).
     * Returns the same items sorted descending by computed `ai_score`.
     * Does NOT create new recommendations.
     *
     * @param  Collection<int, \SaaSPlatform\Modules\Education\Models\EducationRecommendation> $candidates
     * @return Collection<int, array{recommendation: mixed, ai_score: float}>
     */
    public function score(
        Collection             $candidates,
        UserLearningProfileDto $profile,
        Collection             $knowledgeGaps,
    ): Collection;
}
