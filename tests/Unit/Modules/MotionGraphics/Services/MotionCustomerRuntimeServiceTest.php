<?php

namespace Tests\Unit\Modules\MotionGraphics\Services;

use App\Models\Site;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use App\Modules\MotionGraphics\Services\MotionCustomerRuntimeService;
use App\Modules\MotionGraphics\Support\FakeMotionLicenseGateway;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\RuntimeProjectionReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;
use Tests\Support\CreatesMotionTestSite;
use Tests\TestCase;

/**
 * MotionCustomerRuntimeService deliberately never touches
 * MotionAuthorizationInterface (see the class's own docblock: the
 * `tenant.api` machine-to-machine channel has no Laravel Auth::user()),
 * so — unlike every other Motion service's Unit test — nothing here
 * mocks authorization. Only the read contracts and the license gateway
 * are collaborators.
 */
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
        $assets ??= Mockery::mock(MotionAssetReadContract::class);
        $projections ??= Mockery::mock(MotionRuntimeProjectionReadContract::class);

        $service = new MotionCustomerRuntimeService(
            $tenantContext, $auditEvents, new FakeMotionLicenseGateway(allow: $licenseAllows), $assets, $projections,
        );

        return [$service, $assets, $projections];
    }

    private function stubAssetAndProjection(MotionAssetReadContract $assets, MotionRuntimeProjectionReadContract $projections, Site $site, string $assetId, int $version): void
    {
        $assets->shouldReceive('findByCanonicalId')->with($assetId, $version)->andReturn(
            new AssetReadModel($assetId, $version, 'lottie', null, ['checksum' => str_repeat('a', 64)], 'checksum-'.$version, false)
        );
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
        [$service, $assets, $projections] = $this->service($site);
        $this->stubAssetAndProjection($assets, $projections, $site, 'motion-1', 1);

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
        [$service, $assets, $projections] = $this->service($site);
        $this->stubAssetAndProjection($assets, $projections, $site, 'motion-1', 1);

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
        [$service, $assets, $projections] = $this->service($site);
        $this->stubAssetAndProjection($assets, $projections, $site, 'motion-1', 1);

        $manifest = $service->buildManifestForSite($site, new MotionAdapterContext('customer-site:'.$site->id, (string) $site->id, 'corr-1'));
        $array = $manifest->toArray();

        $forbidden = ['source_definition', 'ai_metadata', 'editable_schema', 'ownership', 'credentials', 'storage_path', 'prompt'];
        $encoded = json_encode($array);
        foreach ($forbidden as $key) {
            $this->assertStringNotContainsString($key, $encoded);
        }
    }
}
