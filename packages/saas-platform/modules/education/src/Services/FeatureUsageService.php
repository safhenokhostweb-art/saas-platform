<?php

namespace SaaSPlatform\Modules\Education\Services;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationFeatureRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Repositories\EducationFeatureUsageRepositoryInterface;
use SaaSPlatform\Modules\Education\Contracts\Services\FeatureUsageServiceInterface;

class FeatureUsageService implements FeatureUsageServiceInterface
{
    public function __construct(
        private readonly EducationFeatureRepositoryInterface      $features,
        private readonly EducationFeatureUsageRepositoryInterface $usage,
    ) {}

    public function trackByRoute(string $routeName, int $userId, ?int $siteId): bool
    {
        $feature = $this->features->findByRoute($routeName, null); // platform-level features have site_id=null

        if (! $feature) {
            return false;
        }

        $this->usage->track($userId, $siteId, $feature->module_slug, $feature->feature_slug);

        return true;
    }

    public function track(int $userId, ?int $siteId, string $moduleSlug, string $featureSlug): void
    {
        $this->usage->track($userId, $siteId, $moduleSlug, $featureSlug);
    }

    public function neverUsedFor(int $userId, ?int $siteId): Collection
    {
        return $this->usage->neverUsedFeatures($userId, $siteId);
    }

    public function adoptionStats(?int $siteId = null): array
    {
        return $this->usage->adoptionStats($siteId);
    }
}
