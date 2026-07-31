<?php

namespace Tests\Feature\Modules\MotionGraphics\DeploymentPreparation;

use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Services\MotionDeploymentPreparationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\CreatesActiveSiteAssetPackage;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

class InvalidPackageDeniedTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use CreatesActiveSiteAssetPackage;
    use InteractsWithMotionRuntimeInstallation;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('feature-flags.motion_graphics_integration', true);
        $this->fakeActivityLogger();
    }

    public function test_a_pending_not_yet_active_package_is_rejected(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        $sitePackage->update(['activation_status' => SiteAssetPackage::STATUS_PENDING]);
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('motion-1', 1);

        try {
            $this->app->make(MotionDeploymentPreparationService::class)->prepareDeployment(
                $sitePackage->fresh(['items', 'templateAssetPackage']),
                new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'),
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('site_package_not_active', $e->failureCode);
        }
    }

    public function test_a_package_whose_asset_fails_compatibility_is_rejected(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site, compatibilityRules: ['asset_type' => 'svg']);
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('motion-1', 1); // fake asset is type "lottie", rule requires "svg"

        try {
            $this->app->make(MotionDeploymentPreparationService::class)->prepareDeployment(
                $sitePackage, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1')
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('compatibility_rejected', $e->failureCode);
        }
    }
}
