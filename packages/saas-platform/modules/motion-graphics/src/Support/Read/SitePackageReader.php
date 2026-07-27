<?php

namespace SaaSPlatform\Modules\MotionGraphics\Support\Read;

use Illuminate\Support\Collection;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\SitePackageReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\SiteAssetPackageReadContract;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphicDeployment;

/**
 * Database-backed SiteAssetPackageReadContract. Reuses the same
 * ownership isolation rule as MotionLibraryService::runtimeManifest():
 * a customer-specific asset is only ever included for its own owning
 * site. No other tenant's deployment rows are queried.
 */
final class SitePackageReader implements SiteAssetPackageReadContract
{
    public function __construct(
        private readonly MotionAssetReadContract $assets,
    ) {}

    public function installedAssetsForSite(string $siteIdentifier): array
    {
        $deployments = MotionGraphicDeployment::query()
            ->where('external_site_identifier', $siteIdentifier)
            ->where('is_enabled', true)
            ->with('motionGraphic')
            ->get();

        return $this->resolveDeployedAssets($deployments, $siteIdentifier);
    }

    public function packageState(string $siteIdentifier, string $pageIdentifier, ?string $placementIdentifier = null): ?SitePackageReadModel
    {
        $query = MotionGraphicDeployment::query()
            ->where('external_site_identifier', $siteIdentifier)
            ->where('page_identifier', $pageIdentifier)
            ->where('is_enabled', true);

        if ($placementIdentifier !== null) {
            $query->where('placement_identifier', $placementIdentifier);
        }

        $deployments = $query->with('motionGraphic')->get();
        if ($deployments->isEmpty()) {
            return null;
        }

        $packageId = $siteIdentifier.':'.$pageIdentifier.($placementIdentifier !== null ? ':'.$placementIdentifier : '');

        return new SitePackageReadModel(
            packageId: $packageId,
            templateReference: null,
            siteIdentifier: $siteIdentifier,
            activeAssets: $this->resolveDeployedAssets($deployments, $siteIdentifier),
        );
    }

    /**
     * @param Collection<int, MotionGraphicDeployment> $deployments
     * @return list<AssetReadModel>
     */
    private function resolveDeployedAssets(Collection $deployments, string $siteIdentifier): array
    {
        $assets = [];

        foreach ($deployments as $deployment) {
            $motion = $deployment->motionGraphic;
            if (! $motion instanceof MotionGraphic) {
                continue;
            }
            if ($motion->ownership_type === MotionGraphic::OWNERSHIP_CUSTOMER_SPECIFIC
                && $motion->owner_site_identifier !== $siteIdentifier) {
                continue;
            }

            $version = $deployment->follow_latest_published ? $motion->published_version : $deployment->selected_version;
            if ($version === null) {
                continue;
            }

            $asset = $this->assets->findByCanonicalId($motion->uuid, $version);
            if ($asset !== null) {
                $assets[] = $asset;
            }
        }

        return $assets;
    }
}
