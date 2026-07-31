<?php

namespace Tests\Feature\Modules\MotionGraphics\DeploymentPreparation;

use App\Modules\MotionGraphics\DTO\ForbiddenFieldGuard;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Services\MotionDeploymentPreparationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use ReflectionClass;
use Tests\Support\CreatesActiveSiteAssetPackage;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Task scope item 8: customer cannot prepare deployment, invalid site
 * rejected, unpublished asset rejected, source data leakage prevented,
 * cross tenant package rejected.
 */
class DeploymentPreparationSecurityTest extends TestCase
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

    public function test_customer_cannot_prepare_deployment(): void
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

    public function test_invalid_site_context_is_rejected(): void
    {
        $siteA = $this->createSite('Site A');
        $siteB = $this->createSite('Site B');
        $sitePackage = $this->activeSitePackage($siteA);
        $this->actingAs($this->admin());
        $this->bindTrustedSite($siteA); // trusted tenant is Site A
        $this->bindPublishedAsset('motion-1', 1);

        try {
            // The caller claims Site B while the trusted tenant is Site A.
            $this->app->make(MotionDeploymentPreparationService::class)->prepareDeployment(
                $sitePackage, new MotionAdapterContext('user:1', (string) $siteB->id, 'corr-1')
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('tenant_context_mismatch', $e->failureCode);
        }
    }

    public function test_an_unpublished_asset_is_rejected(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindUnpublishedAsset('motion-1');

        try {
            $this->app->make(MotionDeploymentPreparationService::class)->prepareDeployment(
                $sitePackage, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1')
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('asset_not_available', $e->failureCode);
        }
    }

    public function test_source_and_ai_data_can_never_leak_into_the_manifest(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('motion-1', 1);

        $manifest = $this->app->make(MotionDeploymentPreparationService::class)->prepareDeployment(
            $sitePackage, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1')
        );

        foreach ($manifest->assetReferences as $asset) {
            ForbiddenFieldGuard::assertClean($asset->compatibility, 'compatibility');
        }
        foreach ($manifest->runtimeProjectionReferences as $projection) {
            ForbiddenFieldGuard::assertClean($projection->themeTokens, 'theme_tokens');
            ForbiddenFieldGuard::assertClean($projection->placementRules, 'placement_rules');
        }

        $manifestFields = array_map(
            fn (\ReflectionProperty $p): string => $p->getName(),
            (new ReflectionClass($manifest))->getProperties()
        );
        $this->assertEqualsCanonicalizing(
            ['uuid', 'siteReference', 'templateReference', 'packageVersion', 'assetReferences', 'runtimeProjectionReferences', 'checksum', 'compatibilityResult', 'status'],
            $manifestFields
        );
    }

    public function test_a_cross_tenant_package_is_rejected(): void
    {
        $siteA = $this->createSite('Site A');
        $siteB = $this->createSite('Site B');
        $sitePackageA = $this->activeSitePackage($siteA);
        $this->actingAs($this->admin());
        $this->bindTrustedSite($siteB); // trusted tenant is Site B
        $this->bindPublishedAsset('motion-1', 1);

        try {
            // Site B's context attempts to prepare a deployment for Site A's package.
            $this->app->make(MotionDeploymentPreparationService::class)->prepareDeployment(
                $sitePackageA, new MotionAdapterContext('user:1', (string) $siteB->id, 'corr-1')
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('tenant_isolation_violation', $e->failureCode);
        }
    }
}
