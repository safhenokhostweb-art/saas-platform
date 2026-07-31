<?php

namespace Tests\Feature\Modules\MotionGraphics\Admin;

use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Services\MotionDeploymentPreparationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\CreatesActiveSiteAssetPackage;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Phase 7 task scope item 8: "license failure." The new admin workflow
 * itself (assign/activate/preview) never reaches the License Gateway —
 * only the existing (Phase 5/6) MotionDeploymentPreparationService does,
 * via the real RealMotionLicenseGateway wired since Phase 6. This test
 * proves that a real license failure (the `motion-graphics` module row
 * missing or revoked) still fails deployment preparation closed, so an
 * admin who goes one step further than this phase's UI still hits a real
 * license check, not a silent gap.
 */
class LicenseFailureTest extends TestCase
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

    public function test_deployment_preparation_denies_when_the_module_is_not_registered(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('motion-1', 1);
        // Deliberately no registerMotionGraphicsModule() call.

        try {
            $this->app->make(MotionDeploymentPreparationService::class)->prepareDeployment(
                $sitePackage, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1')
            );
            $this->fail('Expected a license denial.');
        } catch (\App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException $e) {
            $this->assertSame('license_denied', $e->failureCode);
        }
    }

    public function test_deployment_preparation_denies_when_the_module_is_retired(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        $this->registerMotionGraphicsModule();
        \App\Models\Module::where('slug', 'motion-graphics')->update(['retired_at' => now()]);
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('motion-1', 1);

        try {
            $this->app->make(MotionDeploymentPreparationService::class)->prepareDeployment(
                $sitePackage, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1')
            );
            $this->fail('Expected a license denial.');
        } catch (\App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException $e) {
            $this->assertSame('license_denied', $e->failureCode);
        }
    }

    public function test_deployment_preparation_succeeds_once_the_module_is_registered(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        $this->registerMotionGraphicsModule();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('motion-1', 1);

        $manifest = $this->app->make(MotionDeploymentPreparationService::class)->prepareDeployment(
            $sitePackage, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1')
        );

        $this->assertNotNull($manifest);
    }
}
