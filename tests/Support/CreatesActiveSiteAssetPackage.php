<?php

namespace Tests\Support;

use App\Models\Site;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;

/**
 * A minimal, already-active SiteAssetPackage (one item, "motion-1"
 * version 1) for Phase 5 deployment-preparation tests. Pair with
 * InteractsWithMotionRuntimeInstallation::bindPublishedAsset('motion-1', 1)
 * so the read-contract fakes match this fixture's asset id/version.
 */
trait CreatesActiveSiteAssetPackage
{
    protected function activeSitePackage(Site $site, array $compatibilityRules = [], string $templateReference = 'template-1'): SiteAssetPackage
    {
        $templatePackage = TemplateAssetPackage::query()->create([
            'template_reference' => $templateReference,
            'required_assets' => [['asset_id' => 'motion-1', 'version' => 1]],
            'compatibility_rules' => $compatibilityRules,
            'package_version' => 1,
        ]);
        $sitePackage = SiteAssetPackage::query()->create([
            'site_id' => $site->id,
            'template_reference' => $templateReference,
            'template_asset_package_id' => $templatePackage->id,
            'activation_status' => SiteAssetPackage::STATUS_ACTIVE,
        ]);
        SiteAssetPackageItem::query()->create([
            'site_asset_package_id' => $sitePackage->id,
            'asset_id' => 'motion-1',
            'active_version' => 1,
            'bound_checksum' => 'checksum-1',
            'installed_at' => now(),
        ]);

        return $sitePackage->fresh(['items', 'templateAssetPackage']);
    }
}
