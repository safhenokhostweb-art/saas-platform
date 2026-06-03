<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Dto\EducationSearchQuery;

/**
 * Phase 2 placeholder — no live implementation in Phase 1.
 * Swap EducationAiServiceStub with a real provider in EducationServiceProvider.
 */
interface EducationAiServiceInterface
{
    public function suggestArticles(EducationSearchQuery $query): Collection;
    public function generateExcerpt(string $body): ?string;
    public function detectCoverageGaps(string $moduleSlug): array;
    public function suggestRelatedArticles(int $articleId, ?int $siteId): Collection;
}
