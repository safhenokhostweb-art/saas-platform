<?php

namespace SaaSPlatform\Modules\MotionGraphics\Contracts\Read;

use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\SitePackageReadModel;

/**
 * Runtime-safe view of one site's installed Motion Graphics deployments,
 * provider-neutral. See docs/motion-read-contracts/SITE_PACKAGE_READ_MODEL.md.
 * Never returns another tenant's data — every implementation must apply
 * the same ownership isolation as the module's own runtime manifest
 * resolution.
 */
interface SiteAssetPackageReadContract
{
    /**
     * @return list<AssetReadModel>
     */
    public function installedAssetsForSite(string $siteIdentifier): array;

    public function packageState(string $siteIdentifier, string $pageIdentifier, ?string $placementIdentifier = null): ?SitePackageReadModel;
}
