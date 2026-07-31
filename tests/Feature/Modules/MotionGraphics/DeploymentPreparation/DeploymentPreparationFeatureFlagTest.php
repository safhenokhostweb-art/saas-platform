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
 * Task scope item 7: "When OFF: preparation unavailable."
 */
class DeploymentPreparationFeatureFlagTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use CreatesActiveSiteAssetPackage;
    use InteractsWithMotionRuntimeInstallation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeActivityLogger();
    }

    public function test_the_flag_defaults_to_disabled(): void
    {
        // Phase 6: the global default changed from `false` to `null` so a
        // per-site override becomes reachable (docs/motion-integration-phase6-audit.md
        // §6) — but with no override present, HasTenantContext::hasFeature()
        // still falls through to `?? false`, so the *effective* default is
        // unchanged: disabled.
        $this->assertNull(config('feature-flags.motion_graphics_integration'));
        $this->assertFalse((bool) config('feature-flags.motion_graphics_integration'));
    }

    public function test_preparation_is_unavailable_while_disabled(): void
    {
        Config::set('feature-flags.motion_graphics_integration', false);
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);

        $this->expectException(MotionAdapterFailClosedException::class);

        $this->app->make(MotionDeploymentPreparationService::class)->prepareDeployment(
            $sitePackage, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1')
        );
    }
}
