<?php

namespace Tests\Feature\Modules\MotionGraphics\DeploymentPreparation;

use App\Modules\MotionGraphics\Contracts\MotionDeploymentGatewayContract;
use App\Modules\MotionGraphics\Contracts\MotionLicenseGatewayContract;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Services\MotionDeploymentPreparationService;
use App\Modules\MotionGraphics\Support\FakeMotionDeploymentGateway;
use App\Modules\MotionGraphics\Support\FakeMotionLicenseGateway;
use App\Modules\MotionGraphics\Support\RealMotionLicenseGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\CreatesActiveSiteAssetPackage;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Task scope item 9 (Feature): "fake gateway flow" — proves the
 * container-bound fake gateways (task scope items 3 & 5, the only
 * implementations this phase ships) are actually consulted, and that
 * swapping either to deny blocks preparation end to end.
 */
class FakeGatewayFlowTest extends TestCase
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

    public function test_the_default_bound_gateways(): void
    {
        // Phase 6: the Deployment Gateway deliberately remains fake (no
        // real canonical analog exists — docs/motion-integration-phase6-audit.md
        // §4); the License Gateway is now real, wrapping ModuleLicenseAdapter.
        $this->assertInstanceOf(FakeMotionDeploymentGateway::class, $this->app->make(MotionDeploymentGatewayContract::class));
        $this->assertInstanceOf(RealMotionLicenseGateway::class, $this->app->make(MotionLicenseGatewayContract::class));
    }

    public function test_a_denying_deployment_gateway_blocks_preparation(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('motion-1', 1);
        $this->app->instance(MotionDeploymentGatewayContract::class, new FakeMotionDeploymentGateway(allow: false));

        try {
            $this->app->make(MotionDeploymentPreparationService::class)->prepareDeployment(
                $sitePackage, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1')
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('gateway_denied', $e->failureCode);
        }
    }

    public function test_a_denying_license_gateway_blocks_preparation(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('motion-1', 1);
        $this->app->instance(MotionLicenseGatewayContract::class, new FakeMotionLicenseGateway(allow: false));

        try {
            $this->app->make(MotionDeploymentPreparationService::class)->prepareDeployment(
                $sitePackage, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1')
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('license_denied', $e->failureCode);
        }
    }
}
