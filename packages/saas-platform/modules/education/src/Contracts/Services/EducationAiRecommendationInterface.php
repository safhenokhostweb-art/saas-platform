<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

/**
 * Future AI recommendation contract.
 * Bind to EducationAiRecommendationStub until an AI provider is configured.
 */
interface EducationAiRecommendationInterface
{
    /**
     * Given a user's usage profile, return ranked recommendation payloads.
     * Each payload: ['type', 'article_id', 'walkthrough_id', 'learning_path_id', 'priority', 'reason']
     */
    public function recommend(int $userId, ?int $siteId, array $usageProfile): array;

    /** Returns true when an AI provider is actually configured and enabled. */
    public function isEnabled(): bool;
}
