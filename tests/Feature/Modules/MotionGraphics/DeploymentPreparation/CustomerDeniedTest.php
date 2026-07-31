<?php

namespace Tests\Feature\Modules\MotionGraphics\DeploymentPreparation;

use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Services\MotionDeploymentPreparationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\CreatesActiveSiteAssetPackage;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Task scope item 7's authorization matrix (unchanged from Phase 4):
 * deployment preparation is a Platform-Admin-only action.
 */
class CustomerDeniedTest extends TestCase
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

    public function test_a_customer_cannot_prepare_a_deployment(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        $this->actingAs($this->customer($site));
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('motion-1', 1);

        try {
            $this->app->make(MotionDeploymentPreparationService::class)->prepareDeployment(
                $sitePackage, new MotionAdapterContext('user:'.(100 + $site->id), (string) $site->id, 'corr-1')
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('ability_denied', $e->failureCode);
        }
    }

    public function test_a_customer_cannot_approve_a_deployment(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('motion-1', 1);
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');
        $manifest = $this->app->make(MotionDeploymentPreparationService::class)->prepareDeployment($sitePackage, $context);
        $manifest = $this->app->make(MotionDeploymentPreparationService::class)->validateDeployment($manifest, $context);

        $this->actingAs($this->customer($site));

        try {
            $this->app->make(MotionDeploymentPreparationService::class)->approveDeployment(
                $manifest, new MotionAdapterContext('user:'.(100 + $site->id), (string) $site->id, 'corr-1')
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('ability_denied', $e->failureCode);
        }
    }
}
