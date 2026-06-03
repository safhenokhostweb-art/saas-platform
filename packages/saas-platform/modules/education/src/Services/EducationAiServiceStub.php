<?php

namespace SaaSPlatform\Modules\Education\Services;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Contracts\Services\EducationAiServiceInterface;
use SaaSPlatform\Modules\Education\Dto\EducationSearchQuery;

/**
 * Phase 2 stub — all methods return empty/null.
 * Replace this binding in EducationServiceProvider when AI provider is ready.
 */
class EducationAiServiceStub implements EducationAiServiceInterface
{
    public function suggestArticles(EducationSearchQuery $query): Collection
    {
        return collect();
    }

    public function generateExcerpt(string $body): ?string
    {
        return null;
    }

    public function detectCoverageGaps(string $moduleSlug): array
    {
        return [];
    }

    public function suggestRelatedArticles(int $articleId, ?int $siteId): Collection
    {
        return collect();
    }
}
