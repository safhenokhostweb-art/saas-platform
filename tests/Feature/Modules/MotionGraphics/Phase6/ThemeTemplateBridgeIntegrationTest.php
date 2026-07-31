<?php

namespace Tests\Feature\Modules\MotionGraphics\Phase6;

use App\Models\ThemeTemplate;
use App\Modules\MotionGraphics\DTO\DeploymentManifest;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Services\MotionDeploymentPreparationService;
use App\Modules\MotionGraphics\Services\MotionRuntimeInstallationService;
use App\Modules\MotionGraphics\Support\ThemeTemplateReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * Phase 6 (docs/motion-integration-phase6-audit.md): the full chain the
 * task asks for — "Template Builder -> Motion Asset Package Resolver ->
 * Deployment Engine -> Customer Runtime Projection" — exercised through
 * the real container, a real App\Models\ThemeTemplate row (the actual
 * platform Template Builder), and the real license gateway
 * (RealMotionLicenseGateway -> ModuleLicenseAdapter), not fakes.
 */
class ThemeTemplateBridgeIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;
    use InteractsWithMotionRuntimeInstallation;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('feature-flags.motion_graphics_integration', true);
        $this->fakeActivityLogger();
        $this->registerMotionGraphicsModule();
    }

    private function createThemeTemplate(): ThemeTemplate
    {
        return ThemeTemplate::query()->create([
            'type' => 'section',
            'category' => 'hero',
            'name' => 'Hero With Motion',
            'blocks' => [],
        ]);
    }

    public function test_template_selection_flows_through_to_a_ready_deployment_manifest(): void
    {
        $site = $this->createSite();
        $themeTemplate = $this->createThemeTemplate();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('motion-1', 1);
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        // Template Selected -> Motion Asset Package Resolver -> Site
        // Asset Package Creation -> Runtime Projection Generation.
        $templateReference = ThemeTemplateReference::for($themeTemplate);
        $plan = $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
            $templateReference,
            [['asset_id' => 'motion-1', 'version' => 1]],
            [],
            [],
            null,
            $context,
        );

        $this->assertSame(SiteAssetPackage::STATUS_ACTIVE, $plan->sitePackage->activation_status);
        $this->assertSame($templateReference, $plan->sitePackage->template_reference);
        $this->assertCount(1, $plan->projections);

        // -> Deployment Engine (preparation lifecycle), gated by the
        // REAL license gateway this time, not a fake.
        $service = $this->app->make(MotionDeploymentPreparationService::class);
        $manifest = $service->prepareDeployment($plan->sitePackage->fresh(['items', 'templateAssetPackage']), $context);
        $manifest = $service->validateDeployment($manifest, $context);
        $manifest = $service->approveDeployment($manifest, $context);
        $manifest = $service->markReady($manifest, $context);

        $this->assertSame(DeploymentManifest::STATUS_READY, $manifest->status);
        $this->assertSame($templateReference, $manifest->templateReference);

        // -> Customer Runtime Projection: the CustomerRuntimePackage shape
        // carries only the already-allowlisted references, never source data.
        $runtimePackage = $manifest->toCustomerRuntimePackage();
        $this->assertNotEmpty($runtimePackage->assets);
        $this->assertSame('motion-1', $runtimePackage->assets[0]->assetReference->assetId);
    }

    public function test_the_same_theme_template_installs_independently_per_site(): void
    {
        $siteA = $this->createSite('Site A');
        $siteB = $this->createSite('Site B');
        $themeTemplate = $this->createThemeTemplate();
        $templateReference = ThemeTemplateReference::for($themeTemplate);
        $this->bindPublishedAsset('motion-1', 1);

        $this->actingAs($this->admin());
        $this->bindTrustedSite($siteA);
        $planA = $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
            $templateReference, [['asset_id' => 'motion-1', 'version' => 1]], [], [], null,
            new MotionAdapterContext('user:1', (string) $siteA->id, 'corr-a'),
        );

        $this->bindTrustedSite($siteB);
        // MotionRuntimeInstallationService and everything it depends on
        // (SiteAssetPackageActivationService, TemplateAssetPackageService)
        // are bound as container singletons — each captured siteA's
        // TenantContextContract at first resolution above, so rebinding
        // the contract alone would not reach an already-constructed
        // instance. Force a fresh resolve of the whole chain.
        $this->app->forgetInstance(MotionRuntimeInstallationService::class);
        $this->app->forgetInstance(\App\Modules\MotionGraphics\Services\SiteAssetPackageActivationService::class);
        $this->app->forgetInstance(\App\Modules\MotionGraphics\Services\TemplateAssetPackageService::class);
        $planB = $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
            $templateReference, [['asset_id' => 'motion-1', 'version' => 1]], [], [], null,
            new MotionAdapterContext('user:1', (string) $siteB->id, 'corr-b'),
        );

        $this->assertNotSame($planA->sitePackage->id, $planB->sitePackage->id);
        $this->assertSame($siteA->id, $planA->sitePackage->site_id);
        $this->assertSame($siteB->id, $planB->sitePackage->site_id);
        $this->assertSame(2, SiteAssetPackage::query()->where('template_reference', $templateReference)->count());
    }

    public function test_deployment_preparation_fails_closed_when_the_module_is_not_registered(): void
    {
        // No registerMotionGraphicsModule() call for this test's package
        // resolution — simulate the module row being absent/deregistered.
        \App\Models\Module::where('slug', \App\Modules\MotionGraphics\Support\RealMotionLicenseGateway::MODULE_SLUG)->delete();

        $site = $this->createSite();
        $themeTemplate = $this->createThemeTemplate();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('motion-1', 1);
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $plan = $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
            ThemeTemplateReference::for($themeTemplate),
            [['asset_id' => 'motion-1', 'version' => 1]],
            [], [], null, $context,
        );

        $this->expectException(\App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException::class);

        $this->app->make(MotionDeploymentPreparationService::class)->prepareDeployment(
            $plan->sitePackage->fresh(['items', 'templateAssetPackage']), $context
        );
    }
}
