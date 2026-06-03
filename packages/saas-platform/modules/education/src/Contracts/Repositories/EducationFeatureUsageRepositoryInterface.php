<?php

namespace SaaSPlatform\Modules\Education\Contracts\Repositories;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Models\EducationFeatureUsage;

interface EducationFeatureUsageRepositoryInterface
{
    public function track(int $userId, ?int $siteId, string $moduleSlug, string $featureSlug): EducationFeatureUsage;
    public function forUser(int $userId, ?int $siteId): Collection;
    public function neverUsedFeatures(int $userId, ?int $siteId): Collection;
    public function usageCountForFeature(int $userId, ?int $siteId, string $moduleSlug, string $featureSlug): int;
    public function adoptionStats(?int $siteId = null): array;
    public function topFeaturesByUsage(?int $siteId = null, int $limit = 10): Collection;
    public function unusedFeatures(?int $siteId = null): Collection;
}
