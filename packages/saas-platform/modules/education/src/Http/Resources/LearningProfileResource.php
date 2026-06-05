<?php

namespace SaaSPlatform\Modules\Education\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms a UserLearningProfileDto into a JSON-serialisable shape for the mobile SDK.
 *
 * @mixin \SaaSPlatform\Modules\Education\Dto\UserLearningProfileDto
 */
class LearningProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \SaaSPlatform\Modules\Education\Dto\UserLearningProfileDto $dto */
        $dto = $this->resource;

        return [
            'user_id'          => $dto->userId,
            'site_id'          => $dto->siteId,
            'scores'           => [
                'overall'    => $dto->overallScore(),
                'skill'      => $dto->skillScore,
                'onboarding' => $dto->onboardingScore,
                'adoption'   => $dto->adoptionScore,
                'engagement' => $dto->engagementScore,
            ],
            'strongest_modules' => $dto->strongestModules,
            'weakest_modules'   => $dto->weakestModules,
            'last_activity_at'  => $dto->lastActivityAt?->toIso8601String(),
        ];
    }
}
