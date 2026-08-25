<?php

namespace Tests\Feature\Modules\MotionGraphics;

use App\Models\Theme;
use App\Modules\MotionGraphics\DTO\DeploymentManifest;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Services\MotionDeploymentPreparationService;
use App\Modules\MotionGraphics\Services\MotionRuntimeInstallationService;
use App\Modules\MotionGraphics\Support\ThemeReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\CreatesMotionTestSite;
use Tests\Support\InteractsWithMotionRuntimeInstallation;
use Tests\TestCase;

/**
 * The Theme Builder side of the bridge — mirrors
 * tests/Feature/Modules/MotionGraphics/Phase6/ThemeTemplateBridgeIntegrationTest.php
 * exactly, but keyed to a real, sellable App\Models\Theme (a marketplace
 * theme, e.g. Clinic Premium) via ThemeReference instead of a single
 * ThemeTemplate section via ThemeTemplateReference. Proves the same real
 * chain — MotionRuntimeInstallationService, MotionDeploymentPreparationService,
 * the real license gateway — works identically for the Theme Builder's
 * actual sellable-template concept, with zero duplicated logic.
 */
class ThemeMotionBridgeIntegrationTest extends TestCase
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

    private function createClinicPremiumTheme(): Theme
    {
        return Theme::query()->create([
            'name' => 'کلینیک پریمیوم',
            'slug' => 'clinic-premium',
            'description' => 'قالب پزشکی پریمیوم',
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_PUBLISHED,
            'category' => Theme::CATEGORY_MEDICAL,
            'is_marketplace' => true,
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages' => [],
        ]);
    }

    // ── #1 template can reference published Motion; #7 assignment follows install ──

    public function test_theme_selection_flows_through_to_a_ready_deployment_manifest(): void
    {
        $site = $this->createSite();
        $theme = $this->createClinicPremiumTheme();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('medical-hero-intro', 1);
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $templateReference = ThemeReference::for($theme);
        $this->assertSame('theme:clinic-premium', $templateReference);

        $plan = $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
            $templateReference,
            [['asset_id' => 'medical-hero-intro', 'version' => 1]],
            [],
            [],
            null,
            $context,
        );

        $this->assertSame(SiteAssetPackage::STATUS_ACTIVE, $plan->sitePackage->activation_status);
        $this->assertSame($templateReference, $plan->sitePackage->template_reference);
        $this->assertCount(1, $plan->projections);

        $service = $this->app->make(MotionDeploymentPreparationService::class);
        $manifest = $service->prepareDeployment($plan->sitePackage->fresh(['items', 'templateAssetPackage']), $context);
        $manifest = $service->validateDeployment($manifest, $context);
        $manifest = $service->approveDeployment($manifest, $context);
        $manifest = $service->markReady($manifest, $context);

        $this->assertSame(DeploymentManifest::STATUS_READY, $manifest->status);

        $runtimePackage = $manifest->toCustomerRuntimePackage();
        $this->assertNotEmpty($runtimePackage->assets);
        $this->assertSame('medical-hero-intro', $runtimePackage->assets[0]->assetReference->assetId);
    }

    // ── #3 / #8 site cannot use another site's Motion; bespoke-style isolation still holds ──

    public function test_the_same_theme_installs_independently_per_site(): void
    {
        $siteA = $this->createSite('Clinic A');
        $siteB = $this->createSite('Clinic B');
        $theme = $this->createClinicPremiumTheme();
        $templateReference = ThemeReference::for($theme);
        $this->bindPublishedAsset('medical-hero-intro', 1);

        $this->actingAs($this->admin());
        $this->bindTrustedSite($siteA);
        $planA = $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
            $templateReference, [['asset_id' => 'medical-hero-intro', 'version' => 1]], [], [], null,
            new MotionAdapterContext('user:1', (string) $siteA->id, 'corr-a'),
        );

        $this->bindTrustedSite($siteB);
        $this->app->forgetInstance(MotionRuntimeInstallationService::class);
        $this->app->forgetInstance(\App\Modules\MotionGraphics\Services\SiteAssetPackageActivationService::class);
        $this->app->forgetInstance(\App\Modules\MotionGraphics\Services\TemplateAssetPackageService::class);
        $planB = $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
            $templateReference, [['asset_id' => 'medical-hero-intro', 'version' => 1]], [], [], null,
            new MotionAdapterContext('user:1', (string) $siteB->id, 'corr-b'),
        );

        $this->assertNotSame($planA->sitePackage->id, $planB->sitePackage->id);
        $this->assertSame($siteA->id, $planA->sitePackage->site_id);
        $this->assertSame($siteB->id, $planB->sitePackage->site_id);
        $this->assertSame(2, SiteAssetPackage::query()->where('template_reference', $templateReference)->count());

        // Site A's own projection is readable when Site A is the trusted
        // tenant context — but attempting to read it while Site B is the
        // trusted context (i.e. cross-tenant) fails closed, proving Site B
        // can never reach Site A's Motion package.
        $this->bindTrustedSite($siteA);
        $this->app->forgetInstance(MotionRuntimeInstallationService::class);
        $projectA = $this->app->make(MotionRuntimeInstallationService::class)->projectSitePackage(
            $planA->sitePackage, new MotionAdapterContext('user:1', (string) $siteA->id, 'corr-check')
        );
        $this->assertNotEmpty($projectA);

        $this->bindTrustedSite($siteB);
        $this->app->forgetInstance(MotionRuntimeInstallationService::class);
        $this->expectException(MotionAdapterFailClosedException::class);
        $this->app->make(MotionRuntimeInstallationService::class)->projectSitePackage(
            $planA->sitePackage, new MotionAdapterContext('user:1', (string) $siteA->id, 'corr-cross-tenant')
        );
    }

    // ── #2 / #12 unpublished/unlicensed Motion fails closed, license gate enforced ──

    public function test_installation_fails_closed_when_the_motion_module_is_not_licensed(): void
    {
        \App\Models\Module::where('slug', \App\Modules\MotionGraphics\Support\RealMotionLicenseGateway::MODULE_SLUG)->delete();

        $site = $this->createSite();
        $theme = $this->createClinicPremiumTheme();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('medical-hero-intro', 1);
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $plan = $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
            ThemeReference::for($theme),
            [['asset_id' => 'medical-hero-intro', 'version' => 1]],
            [], [], null, $context,
        );

        $this->expectException(MotionAdapterFailClosedException::class);

        $this->app->make(MotionDeploymentPreparationService::class)->prepareDeployment(
            $plan->sitePackage->fresh(['items', 'templateAssetPackage']), $context
        );
    }

    public function test_installation_fails_closed_when_the_required_asset_is_not_published(): void
    {
        $site = $this->createSite();
        $theme = $this->createClinicPremiumTheme();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindUnpublishedAsset('medical-hero-intro');
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $this->expectException(MotionAdapterFailClosedException::class);

        $this->app->make(MotionRuntimeInstallationService::class)->installTemplateForSite(
            ThemeReference::for($theme),
            [['asset_id' => 'medical-hero-intro', 'version' => 1]],
            [], [], null, $context,
        );
    }

    // ── #9 template update preserves the site's activated package (idempotent, not a silent overwrite) ──

    public function test_reinstalling_the_same_theme_for_a_site_is_idempotent_not_destructive(): void
    {
        $site = $this->createSite();
        $theme = $this->createClinicPremiumTheme();
        $this->actingAs($this->admin());
        $this->bindTrustedSite($site);
        $this->bindPublishedAsset('medical-hero-intro', 1);
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $service = $this->app->make(MotionRuntimeInstallationService::class);
        $first = $service->installTemplateForSite(
            ThemeReference::for($theme), [['asset_id' => 'medical-hero-intro', 'version' => 1]], [], [], null, $context
        );
        $second = $service->installTemplateForSite(
            ThemeReference::for($theme), [['asset_id' => 'medical-hero-intro', 'version' => 1]], [], [], null, $context
        );

        $this->assertSame($first->sitePackage->id, $second->sitePackage->id);
        $this->assertTrue($second->wasAlreadyUpToDate);
    }

    // ── #11 a Theme with no declared Motion package is entirely unaffected ──

    public function test_a_theme_without_a_declared_motion_package_is_unaffected(): void
    {
        $theme = $this->createClinicPremiumTheme();

        $this->assertArrayNotHasKey('motion', $theme->settings ?? []);
        $this->assertSame(0, SiteAssetPackage::query()->where('template_reference', ThemeReference::for($theme))->count());
    }
}
