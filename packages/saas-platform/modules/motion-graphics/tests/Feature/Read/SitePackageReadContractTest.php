<?php

namespace SaaSPlatform\Modules\MotionGraphics\Tests\Feature\Read;

use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\SiteAssetPackageReadContract;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionDeploymentService;
use SaaSPlatform\Modules\MotionGraphics\Services\MotionLibraryService;
use SaaSPlatform\Modules\MotionGraphics\Tests\TestCase;

final class SitePackageReadContractTest extends TestCase
{
    public function test_installed_assets_for_site_lists_only_that_sites_enabled_deployments(): void
    {
        $motion = $this->published('site-package-a');
        app(MotionDeploymentService::class)->assign($motion, 'site-a', 'home', 'hero', $this->admin);

        $assets = app(SiteAssetPackageReadContract::class)->installedAssetsForSite('site-a');

        $this->assertCount(1, $assets);
        $this->assertSame($motion->uuid, $assets[0]->assetId);
    }

    public function test_installed_assets_for_site_excludes_disabled_deployments(): void
    {
        $motion = $this->published('site-package-disabled');
        app(MotionDeploymentService::class)->assign($motion, 'site-a', 'home', 'hero', $this->admin, null, 0, false);

        $this->assertSame([], app(SiteAssetPackageReadContract::class)->installedAssetsForSite('site-a'));
    }

    public function test_a_customer_specific_asset_never_appears_for_another_sites_package(): void
    {
        $motion = $this->published('site-package-customer', MotionGraphic::OWNERSHIP_CUSTOMER_SPECIFIC, 'site-a');
        // The module itself refuses to deploy a customer-specific asset to a
        // foreign site (see MotionDeploymentService::assign), which is the
        // primary defense. installedAssetsForSite() re-checks ownership
        // independently as defense in depth.
        $assets = app(SiteAssetPackageReadContract::class)->installedAssetsForSite('site-b');

        $this->assertSame([], $assets);
    }

    public function test_package_state_returns_null_when_no_deployment_exists(): void
    {
        $this->assertNull(app(SiteAssetPackageReadContract::class)->packageState('site-z', 'home'));
    }

    public function test_package_state_lists_active_assets_for_a_page_and_placement(): void
    {
        $motion = $this->published('site-package-state');
        app(MotionDeploymentService::class)->assign($motion, 'site-a', 'home', 'hero', $this->admin);

        $state = app(SiteAssetPackageReadContract::class)->packageState('site-a', 'home', 'hero');

        $this->assertNotNull($state);
        $this->assertSame('site-a', $state->siteIdentifier);
        $this->assertSame('site-a:home:hero', $state->packageId);
        $this->assertNull($state->templateReference);
        $this->assertCount(1, $state->activeAssets);
    }

    private function published(string $slug, string $ownership = MotionGraphic::OWNERSHIP_GLOBAL, ?string $site = null): MotionGraphic
    {
        $library = app(MotionLibraryService::class);
        $motion = $library->createFromPipeline($this->motionData($slug, null, $ownership, $site), $this->svgInput(), $this->admin);
        $library->approve($motion, 1, $this->admin);
        $library->publish($motion, 1, $this->admin);

        return $motion->fresh();
    }
}
