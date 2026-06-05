<?php

namespace SaaSPlatform\Modules\Education\Contracts\Services;

use Illuminate\Support\Collection;

interface ContentReadinessServiceInterface
{
    /**
     * Evaluate content coverage for every module in scope.
     *
     * Each entry: {
     *   module_slug:     string,
     *   readiness_score: float (0–100),
     *   missing_content: string[]   -- human-readable gap labels
     * }
     *
     * @return Collection<int, array{module_slug: string, readiness_score: float, missing_content: string[]}>
     */
    public function evaluate(?int $siteId): Collection;

    /**
     * Evaluate a single module.
     *
     * @return array{module_slug: string, readiness_score: float, missing_content: string[]}
     */
    public function evaluateModule(string $moduleSlug, ?int $siteId): array;
}
