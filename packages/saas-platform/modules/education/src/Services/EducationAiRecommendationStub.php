<?php

namespace SaaSPlatform\Modules\Education\Services;

use SaaSPlatform\Modules\Education\Contracts\Services\EducationAiRecommendationInterface;

class EducationAiRecommendationStub implements EducationAiRecommendationInterface
{
    public function recommend(int $userId, ?int $siteId, array $usageProfile): array
    {
        // Stub — returns empty recommendations until an AI provider is wired.
        return [];
    }

    public function isEnabled(): bool
    {
        return false;
    }
}
