<?php

namespace Tests\Unit\Modules\MotionGraphics\Services;

use App\Models\Site;
use App\Modules\MotionGraphics\Contracts\MotionAssetResolverInterface;
use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\DTO\AuthorizationDecision;
use App\Modules\MotionGraphics\DTO\CompatibilityDecision;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use App\Modules\MotionGraphics\Services\MotionCustomerRuntimeService;
use App\Modules\MotionGraphics\Services\MotionRuntimeInstallationService;
use App\Modules\MotionGraphics\Services\SiteAssetPackageActivationService;
use App\Modules\MotionGraphics\Services\TemplateAssetPackageService;
use App\Modules\MotionGraphics\Support\FakeMotionLicenseGateway;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use App\Modules\MotionGraphics\Support\TemplateBuilderCompatibilityValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\RuntimeProjectionReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionVersionReadContract;
use Tests\Support\CreatesMotionTestSite;
use Tests\TestCase;

class MotionCustomerRuntimeServiceTest extends TestCase
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
        $tenantContext->shouldReceive('set')->withAnyArgs()->andReturnNull();

        return $tenantContext;
    }

    private function allowingAuthorization(): MotionAuthorizationInterface
    {
        $authorization = Mockery::mock(MotionAuthorizationInterface::class);
        $authorization->shouldReceive('authorize')->andReturn(AuthorizationDecision::allow());

        return $authorization;
    }

    private function activePackage(Site $site, string $assetId = 'motion-1', int $version = 1): SiteAssetPackage
    {
        $templatePackage = TemplateAssetPackage::query()->create([
            'template_reference' => 'template-1',
            'required_assets' => [['asset_id' => $assetId, 'version' => $version]],
            'package_version' => 1,
        ]);
        $sitePackage = SiteAssetPackage::query()->create([
            'site_id' => $site->id,
            'template_reference' => 'template-1',
            'template_asset_package_id' => $templatePackage->id,
            'activation_status' => SiteAssetPackage::STATUS_ACTIVE,
            'activated_at' => now(),
        ]);
        SiteAssetPackageItem::query()->create([
            'site_asset_package_id' => $sitePackage->id,
            'asset_id' => $assetId,
            'active_version' => $version,
            'bound_checksum' => 'checksum-1',
            'installed_at' => now(),
        ]);

        return $sitePackage->fresh(['items', 'templateAssetPackage']);
    }

    /**
     * @return array{0: MotionCustomerRuntimeService, 1: MotionAssetReadContract, 2: MotionRuntimeProjectionReadContract}
     */
    private function service(
        Site $site,
        bool $flagEnabled = true,
        bool $licenseAllows = true,
        ?MotionAssetReadContract $assets = null,
        ?MotionRuntimeProjectionReadContract $projections = null,
    ): array {
        $tenantContext = $this->tenantContext($site, $flagEnabled);
        $auditEvents = Mockery::mock(MotionAuditEvents::class)->shouldIgnoreMissing();
        $authorization = $this->allowingAuthorization();
        $assets ??= Mockery::mock(MotionAssetReadContract::class);
        $projections ??= Mockery::mock(MotionRuntimeProjectionReadContract::class);

        $templatePackages = new TemplateAssetPackageService($tenantContext, $auditEvents, $authorization, $assets);
        $assetResolver = Mockery::mock(MotionAssetResolverInterface::class);
        $assetResolver->shouldReceive('validateCompatibility')->andReturn(CompatibilityDecision::compatible());
        $activation = new SiteAssetPackageActivationService(
            $tenantContext, $auditEvents, $authorization, $assetResolver, $assets,
            Mockery::mock(MotionVersionReadContract::class), $projections,
        );
        $installation = new MotionRuntimeInstallationService(
            $tenantContext, $auditEvents, $authorization, $templatePackages, $activation, $assets, $projections,
            new TemplateBuilderCompatibilityValidator(),
        );

        $service = new MotionCustomerRuntimeService(
            $tenantContext, $auditEvents, new FakeMotionLicenseGateway(allow: $licenseAllows), $installation,
        );

        return [$service, $assets, $projections];
    }

    private function stubProjection(MotionRuntimeProjectionReadContract $projections, Site $site, string $assetId, int $version): void
    {
        $projections->shouldReceive('projectAsset')->with($assetId, (string) $site->id)->andReturn(
            new RuntimeProjectionReadModel(
                assetReference: new AssetReadModel($assetId, $version, 'lottie', null, ['checksum' => str_repeat('a', 64)], 'checksum-'.$version, false),
                publishedVersion: $version,
                optimizedReference: '/motion/'.$assetId.'/v'.$version.'/output.json',
                themeTokens: ['primary' => '#111'],
                placementRules: ['section' => 'hero'],
            )
        );
    }

    public function test_it_builds_a_manifest_for_an_active_package(): void
    {
        $site = $this->createSite();
        $package = $this->activePackage($site);
        [$service, , $projections] = $this->service($site);
        $this->stubProjection($projections, $site, 'motion-1', 1);

        $manifest = $service->buildManifestForSite($site, new MotionAdapterContext('customer-site:'.$site->id, (string) $site->id, 'corr-1'));

        $this->assertSame('1.0', $manifest->schemaVersion);
        $this->assertSame((string) $site->id, $manifest->siteReference);
        $this->assertSame('template-1', $manifest->templateReference);
        $this->assertCount(1, $manifest->assets);
        $this->assertSame('motion-1', $manifest->assets[0]->assetId);
        $this->assertSame('lottie', $manifest->assets[0]->assetType);
        $this->assertNotEmpty($manifest->manifestChecksum);
        unset($package);
    }

    public function test_checksum_is_stable_across_two_builds_of_the_same_state(): void
    {
        $site = $this->createSite();
        $this->activePackage($site);
        [$service, , $projections] = $this->service($site);
        $this->stubProjection($projections, $site, 'motion-1', 1);

        $context = new MotionAdapterContext('customer-site:'.$site->id, (string) $site->id, 'corr-1');
        $first = $service->buildManifestForSite($site, $context);
        $second = $service->buildManifestForSite($site, $context);

        $this->assertSame($first->manifestChecksum, $second->manifestChecksum);
    }

    public function test_it_fails_closed_when_the_flag_is_disabled(): void
    {
        $site = $this->createSite();
        $this->activePackage($site);
        [$service] = $this->service($site, flagEnabled: false);

        try {
            $service->buildManifestForSite($site, new MotionAdapterContext('customer-site:'.$site->id, (string) $site->id, 'corr-1'));
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('motion_adapter_disabled', $e->failureCode);
        }
    }

    public function test_it_fails_closed_when_the_license_denies(): void
    {
        $site = $this->createSite();
        $this->activePackage($site);
        [$service] = $this->service($site, licenseAllows: false);

        try {
            $service->buildManifestForSite($site, new MotionAdapterContext('customer-site:'.$site->id, (string) $site->id, 'corr-1'));
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('license_denied', $e->failureCode);
        }
    }

    public function test_it_fails_closed_when_no_active_package_exists(): void
    {
        $site = $this->createSite();
        [$service] = $this->service($site);

        try {
            $service->buildManifestForSite($site, new MotionAdapterContext('customer-site:'.$site->id, (string) $site->id, 'corr-1'));
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('no_active_package', $e->failureCode);
        }
    }

    public function test_the_manifest_never_carries_forbidden_fields(): void
    {
        $site = $this->createSite();
        $this->activePackage($site);
        [$service, , $projections] = $this->service($site);
        $this->stubProjection($projections, $site, 'motion-1', 1);

        $manifest = $service->buildManifestForSite($site, new MotionAdapterContext('customer-site:'.$site->id, (string) $site->id, 'corr-1'));
        $array = $manifest->toArray();

        $forbidden = ['source_definition', 'ai_metadata', 'editable_schema', 'ownership', 'credentials', 'storage_path', 'prompt'];
        $encoded = json_encode($array);
        foreach ($forbidden as $key) {
            $this->assertStringNotContainsString($key, $encoded);
        }
    }
}
