<?php

namespace Tests\Feature\Modules\MotionGraphics\DeploymentPreparation;

use App\Modules\MotionGraphics\DTO\DeploymentManifest;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Services\MotionDeploymentPreparationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\CreatesActiveSiteAssetPackage;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

class AdminPreparationSuccessTest extends TestCase
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
        $this->registerMotionGraphicsModule();
    }

    public function test_an_admin_can_prepare_a_deployment_manifest(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('motion-1', 1);

        $manifest = $this->app->make(MotionDeploymentPreparationService::class)->prepareDeployment(
            $sitePackage, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1')
        );

        $this->assertSame(DeploymentManifest::STATUS_PREPARED, $manifest->status);
        $this->assertSame((string) $site->id, $manifest->siteReference);
        $this->assertCount(1, $manifest->runtimeProjectionReferences);
    }
}
