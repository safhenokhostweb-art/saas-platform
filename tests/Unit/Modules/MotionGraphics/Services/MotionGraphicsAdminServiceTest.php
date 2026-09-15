<?php

namespace Tests\Unit\Modules\MotionGraphics\Services;

use App\Models\Site;
use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\DTO\AuthorizationDecision;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use App\Modules\MotionGraphics\Services\MotionGraphicsAdminService;
use App\Modules\MotionGraphics\Services\MotionRuntimeInstallationService;
use App\Modules\MotionGraphics\Services\SiteAssetPackageActivationService;
use App\Modules\MotionGraphics\Services\TemplateAssetPackageService;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use App\Modules\MotionGraphics\Support\TemplateBuilderCompatibilityValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\RuntimeProjectionReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetLibraryBrowseContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetLibrarySummaryContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionVersionReadContract;
use App\Modules\MotionGraphics\Contracts\MotionAssetResolverInterface;
use App\Modules\MotionGraphics\DTO\CompatibilityDecision;
use Tests\Support\CreatesMotionTestSite;
use Tests\TestCase;

class MotionGraphicsAdminServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function tenantContext(Site $site, bool $flagEnabled = true): TenantContextContract
    {
        $tenantContext = Mockery::mock(TenantContextContract::class);
        $tenantContext->shouldReceive('hasFeature')->with('motion_graphics_integration')->andReturn($flagEnabled);
        $tenantContext->shouldReceive('site')->andReturn($site);

        return $tenantContext;
    }

    private function authorizationAllowing(?string $onlyAbility = null): MotionAuthorizationInterface
    {
        $authorization = Mockery::mock(MotionAuthorizationInterface::class);

        if ($onlyAbility === null) {
            $authorization->shouldReceive('authorize')->andReturn(AuthorizationDecision::allow());

            return $authorization;
        }

        $authorization->shouldReceive('authorize')
            ->withArgs(fn ($actorId, $ability) => $ability === $onlyAbility)
            ->andReturn(AuthorizationDecision::allow());
        $authorization->shouldReceive('authorize')
            ->withArgs(fn ($actorId, $ability) => $ability !== $onlyAbility)
            ->andReturn(AuthorizationDecision::deny('ability_denied', 'not allowed'));

        return $authorization;
    }

    private function compatibleAssetResolver(): MotionAssetResolverInterface
    {
        $resolver = Mockery::mock(MotionAssetResolverInterface::class);
        $resolver->shouldReceive('validateCompatibility')->andReturn(CompatibilityDecision::compatible());

        return $resolver;
    }

    /**
     * @return array{0: MotionGraphicsAdminService, 1: MotionAssetReadContract, 2: MotionRuntimeProjectionReadContract, 3: MotionAssetLibrarySummaryContract, 4: MotionAuthorizationInterface}
     */
    private function service(
        Site $site,
        bool $flagEnabled = true,
        ?MotionAuthorizationInterface $authorization = null,
        ?MotionAssetReadContract $assets = null,
        ?MotionRuntimeProjectionReadContract $projections = null,
        ?MotionAssetLibrarySummaryContract $assetLibrary = null,
    ): array {
        $tenantContext = $this->tenantContext($site, $flagEnabled);
        $auditEvents = Mockery::mock(MotionAuditEvents::class)->shouldIgnoreMissing();
        $authorization ??= $this->authorizationAllowing();
        $assets ??= Mockery::mock(MotionAssetReadContract::class);
        $projections ??= Mockery::mock(MotionRuntimeProjectionReadContract::class);
        $assetLibrary ??= Mockery::mock(MotionAssetLibrarySummaryContract::class)->shouldIgnoreMissing(0);
        // TEST-HARNESS-DB-01: MotionGraphicsAdminService's constructor grew
        // two params (MotionRuntimeProjectionReadContract, this one) after
        // this helper was last updated — $projections was already built
        // above (and threaded into $activation/$installation) but never
        // actually passed into `new MotionGraphicsAdminService(...)` below,
        // and this contract had no mock at all yet.
        $libraryBrowse = Mockery::mock(MotionAssetLibraryBrowseContract::class)->shouldIgnoreMissing(['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 0]);

        $templatePackages = new TemplateAssetPackageService($tenantContext, $auditEvents, $authorization, $assets);
        $activation = new SiteAssetPackageActivationService(
            $tenantContext, $auditEvents, $authorization, $this->compatibleAssetResolver(), $assets,
            Mockery::mock(MotionVersionReadContract::class), $projections,
        );
        $installation = new MotionRuntimeInstallationService(
            $tenantContext, $auditEvents, $authorization, $templatePackages, $activation, $assets, $projections,
            new TemplateBuilderCompatibilityValidator(),
        );

        $admin = new MotionGraphicsAdminService(
            $tenantContext, $auditEvents, $authorization, $assetLibrary, $templatePackages, $installation,
            new TemplateBuilderCompatibilityValidator(), $assets, $projections, $libraryBrowse,
        );

        return [$admin, $assets, $projections, $assetLibrary, $authorization];
    }

    private function stubAsset(MotionAssetReadContract $assets, string $assetId, int $version): void
    {
        $assets->shouldReceive('resolveActiveVersion')->with($assetId)->andReturn($version)->byDefault();
        $assets->shouldReceive('findByCanonicalId')->with($assetId, $version)->andReturn(
            new AssetReadModel($assetId, $version, 'lottie', null, [], 'checksum-'.$version, false)
        )->byDefault();
    }

    public function test_dashboard_summary_requires_view_library_ability(): void
    {
        $site = $this->createSite();
        [$admin] = $this->service($site, authorization: $this->authorizationAllowing('motion.manage_template_package'));

        $this->expectException(MotionAdapterFailClosedException::class);

        $admin->dashboardSummary(new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'));
    }

    public function test_dashboard_summary_reports_real_counts(): void
    {
        $site = $this->createSite();
        $templatePackage = TemplateAssetPackage::query()->create([
            'template_reference' => 'template-1',
            'required_assets' => [],
            'package_version' => 1,
        ]);
        SiteAssetPackage::query()->create([
            'site_id' => $site->id,
            'template_reference' => 'template-1',
            'template_asset_package_id' => $templatePackage->id,
            'activation_status' => SiteAssetPackage::STATUS_ACTIVE,
        ]);
        $assetLibrary = Mockery::mock(MotionAssetLibrarySummaryContract::class);
        $assetLibrary->shouldReceive('countPublishedAssets')->andReturn(7);
        [$admin] = $this->service($site, assetLibrary: $assetLibrary);

        $summary = $admin->dashboardSummary(new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'));

        $this->assertSame(7, $summary->totalAssets);
        $this->assertSame(1, $summary->assignedPackages);
        $this->assertSame(1, $summary->activatedInternalSites);
    }

    public function test_it_fails_closed_when_the_flag_is_disabled(): void
    {
        $site = $this->createSite();
        [$admin] = $this->service($site, flagEnabled: false);

        $this->expectException(MotionAdapterFailClosedException::class);

        $admin->dashboardSummary(new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'));
    }

    public function test_it_rejects_a_context_claiming_a_different_site_than_the_trusted_tenant(): void
    {
        $site = $this->createSite('Site A');
        $otherSite = $this->createSite('Site B');
        [$admin] = $this->service($site);

        try {
            $admin->dashboardSummary(new MotionAdapterContext('user:1', (string) $otherSite->id, 'corr-1'));
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('tenant_context_mismatch', $e->failureCode);
        }
    }

    public function test_assign_package_to_template_requires_manage_template_package_ability(): void
    {
        $site = $this->createSite();
        [$admin, $assets] = $this->service($site, authorization: $this->authorizationAllowing('motion.view_library'));
        $this->stubAsset($assets, 'motion-1', 1);

        $this->expectException(MotionAdapterFailClosedException::class);

        $admin->assignPackageToTemplate('template-1', [['asset_id' => 'motion-1']], [], [], new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'));
    }

    public function test_assign_package_to_template_delegates_and_persists(): void
    {
        $site = $this->createSite();
        [$admin, $assets] = $this->service($site);
        $this->stubAsset($assets, 'motion-1', 1);

        $package = $admin->assignPackageToTemplate(
            'template-1', [['asset_id' => 'motion-1']], [], [], new MotionAdapterContext('user:1', (string) $site->id, 'corr-1')
        );

        $this->assertSame('template-1', $package->template_reference);
        $this->assertSame(1, TemplateAssetPackage::query()->count());
    }

    public function test_activate_for_internal_site_requires_activate_site_package_ability(): void
    {
        $site = $this->createSite();
        [$admin, $assets] = $this->service($site, authorization: $this->authorizationAllowing('motion.view_library'));
        $this->stubAsset($assets, 'motion-1', 1);

        $this->expectException(MotionAdapterFailClosedException::class);

        $admin->activateForInternalSite('template-1', [['asset_id' => 'motion-1']], [], [], null, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'));
    }

    public function test_activate_for_internal_site_creates_an_active_package(): void
    {
        $site = $this->createSite();
        [$admin, $assets, $projections] = $this->service($site);
        $this->stubAsset($assets, 'motion-1', 1);
        $projections->shouldReceive('projectAsset')->with('motion-1', (string) $site->id)->andReturn(
            new RuntimeProjectionReadModel(
                assetReference: new AssetReadModel('motion-1', 1, 'lottie', null, [], 'checksum-1', false),
                publishedVersion: 1,
                optimizedReference: '/motion/x/v1/a.json',
            )
        );

        $plan = $admin->activateForInternalSite('template-1', [['asset_id' => 'motion-1']], [], [], null, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'));

        $this->assertSame(SiteAssetPackage::STATUS_ACTIVE, $plan->sitePackage->activation_status);
        $this->assertCount(1, $plan->projections);
    }

    public function test_preview_runtime_projection_rejects_a_package_belonging_to_another_site(): void
    {
        $siteA = $this->createSite('Site A');
        $siteB = $this->createSite('Site B');
        $templatePackage = TemplateAssetPackage::query()->create([
            'template_reference' => 'template-1', 'required_assets' => [], 'package_version' => 1,
        ]);
        $sitePackageA = SiteAssetPackage::query()->create([
            'site_id' => $siteA->id, 'template_reference' => 'template-1',
            'template_asset_package_id' => $templatePackage->id, 'activation_status' => SiteAssetPackage::STATUS_ACTIVE,
        ]);
        [$admin] = $this->service($siteB);

        try {
            $admin->previewRuntimeProjection($sitePackageA, new MotionAdapterContext('user:1', (string) $siteB->id, 'corr-1'));
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('tenant_isolation_violation', $e->failureCode);
        }
    }

    public function test_preview_runtime_projection_returns_the_projection_for_an_owned_package(): void
    {
        $site = $this->createSite();
        $templatePackage = TemplateAssetPackage::query()->create([
            'template_reference' => 'template-1', 'required_assets' => [], 'package_version' => 1,
        ]);
        $sitePackage = SiteAssetPackage::query()->create([
            'site_id' => $site->id, 'template_reference' => 'template-1',
            'template_asset_package_id' => $templatePackage->id, 'activation_status' => SiteAssetPackage::STATUS_ACTIVE,
        ]);
        SiteAssetPackageItem::query()->create([
            'site_asset_package_id' => $sitePackage->id, 'asset_id' => 'motion-1', 'active_version' => 1,
            'bound_checksum' => 'checksum-1', 'installed_at' => now(),
        ]);
        [$admin, , $projections] = $this->service($site);
        $projections->shouldReceive('projectAsset')->with('motion-1', (string) $site->id)->andReturn(
            new RuntimeProjectionReadModel(
                assetReference: new AssetReadModel('motion-1', 1, 'lottie', null, [], 'checksum-1', false),
                publishedVersion: 1,
                optimizedReference: '/motion/x/v1/a.json',
            )
        );

        $result = $admin->previewRuntimeProjection($sitePackage->fresh(['items']), new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'));

        $this->assertCount(1, $result);
        $this->assertSame('motion-1', $result[0]->assetReference->assetId);
    }
}
