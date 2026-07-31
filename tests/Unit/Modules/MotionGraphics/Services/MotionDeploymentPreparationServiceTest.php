<?php

namespace Tests\Unit\Modules\MotionGraphics\Services;

use App\Models\Site;
use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\DTO\AuthorizationDecision;
use App\Modules\MotionGraphics\DTO\DeploymentManifest;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use App\Modules\MotionGraphics\Services\MotionDeploymentPreparationService;
use App\Modules\MotionGraphics\Support\FakeMotionDeploymentGateway;
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
use Tests\Support\CreatesMotionTestSite;
use Tests\TestCase;

class MotionDeploymentPreparationServiceTest extends TestCase
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

    private function allowingAuthorization(): MotionAuthorizationInterface
    {
        $authorization = Mockery::mock(MotionAuthorizationInterface::class);
        $authorization->shouldReceive('authorize')->andReturn(AuthorizationDecision::allow());

        return $authorization;
    }

    private function activeSitePackage(Site $site, array $compatibilityRules = []): SiteAssetPackage
    {
        $templatePackage = TemplateAssetPackage::query()->create([
            'template_reference' => 'template-1',
            'required_assets' => [['asset_id' => 'motion-1', 'version' => 1]],
            'compatibility_rules' => $compatibilityRules,
            'package_version' => 1,
        ]);
        $sitePackage = SiteAssetPackage::query()->create([
            'site_id' => $site->id,
            'template_reference' => 'template-1',
            'template_asset_package_id' => $templatePackage->id,
            'activation_status' => SiteAssetPackage::STATUS_ACTIVE,
        ]);
        SiteAssetPackageItem::query()->create([
            'site_asset_package_id' => $sitePackage->id,
            'asset_id' => 'motion-1',
            'active_version' => 1,
            'bound_checksum' => 'checksum-1',
            'installed_at' => now(),
        ]);

        return $sitePackage->fresh(['items', 'templateAssetPackage']);
    }

    /**
     * @return array{0: MotionDeploymentPreparationService, 1: MotionAssetReadContract, 2: MotionRuntimeProjectionReadContract}
     */
    private function service(
        Site $site,
        bool $flagEnabled = true,
        ?MotionAuthorizationInterface $authorization = null,
        bool $licenseAllows = true,
        bool $gatewayAllows = true,
        ?MotionAssetReadContract $assets = null,
        ?MotionRuntimeProjectionReadContract $projections = null,
    ): array {
        $tenantContext = $this->tenantContext($site, $flagEnabled);
        $auditEvents = Mockery::mock(MotionAuditEvents::class)->shouldIgnoreMissing();
        $authorization ??= $this->allowingAuthorization();
        $assets ??= Mockery::mock(MotionAssetReadContract::class);
        $assets->shouldReceive('findByCanonicalId')->with('motion-1', 1)->andReturn(
            new AssetReadModel('motion-1', 1, 'lottie', null, [], 'checksum-1', false)
        )->byDefault();
        $projections ??= Mockery::mock(MotionRuntimeProjectionReadContract::class);
        $projections->shouldReceive('projectAsset')->with('motion-1', (string) $site->id)->andReturn(
            new RuntimeProjectionReadModel(
                assetReference: new AssetReadModel('motion-1', 1, 'lottie', null, [], 'checksum-1', false),
                publishedVersion: 1,
                optimizedReference: '/motion/x/v1/a.json',
            )
        )->byDefault();

        $service = new MotionDeploymentPreparationService(
            $tenantContext,
            $auditEvents,
            $authorization,
            new FakeMotionLicenseGateway($licenseAllows),
            new FakeMotionDeploymentGateway($gatewayAllows),
            $assets,
            $projections,
            new TemplateBuilderCompatibilityValidator(),
        );

        return [$service, $assets, $projections];
    }

    public function test_it_prepares_a_manifest_with_the_expected_content(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        [$service] = $this->service($site);

        $manifest = $service->prepareDeployment($sitePackage, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'));

        $this->assertSame(DeploymentManifest::STATUS_PREPARED, $manifest->status);
        $this->assertSame('template-1', $manifest->templateReference);
        $this->assertSame((string) $site->id, $manifest->siteReference);
        $this->assertCount(1, $manifest->assetReferences);
        $this->assertCount(1, $manifest->runtimeProjectionReferences);
        $this->assertNotEmpty($manifest->checksum);
        $this->assertTrue($manifest->compatibilityResult->valid);
    }

    public function test_the_checksum_is_stable_for_identical_content(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        [$service] = $this->service($site);
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $first = $service->prepareDeployment($sitePackage, $context);
        $second = $service->prepareDeployment($sitePackage->fresh(['items', 'templateAssetPackage']), $context);

        $this->assertNotSame($first->uuid, $second->uuid);
        $this->assertSame($first->checksum, $second->checksum);
    }

    public function test_it_rejects_when_the_license_gateway_denies_an_asset(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        [$service] = $this->service($site, licenseAllows: false);

        try {
            $service->prepareDeployment($sitePackage, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'));
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('license_denied', $e->failureCode);
        }
    }

    public function test_it_rejects_when_the_deployment_gateway_denies(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        [$service] = $this->service($site, gatewayAllows: false);

        try {
            $service->prepareDeployment($sitePackage, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'));
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('gateway_denied', $e->failureCode);
        }
    }

    public function test_it_rejects_an_inactive_site_package(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        $sitePackage->update(['activation_status' => SiteAssetPackage::STATUS_PENDING]);
        [$service] = $this->service($site);

        try {
            $service->prepareDeployment($sitePackage->fresh(['items', 'templateAssetPackage']), new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'));
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('site_package_not_active', $e->failureCode);
        }
    }

    public function test_it_fails_closed_when_the_flag_is_disabled(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        [$service] = $this->service($site, false);

        $this->expectException(MotionAdapterFailClosedException::class);

        $service->prepareDeployment($sitePackage, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'));
    }

    public function test_it_fails_closed_when_authorization_denies(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        $authorization = Mockery::mock(MotionAuthorizationInterface::class);
        $authorization->shouldReceive('authorize')->andReturn(AuthorizationDecision::deny('ability_denied', 'no'));
        [$service] = $this->service($site, true, $authorization);

        try {
            $service->prepareDeployment($sitePackage, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'));
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('ability_denied', $e->failureCode);
        }
    }

    public function test_the_full_lifecycle_progresses_through_every_state(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        [$service] = $this->service($site);
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $manifest = $service->prepareDeployment($sitePackage, $context);
        $this->assertSame(DeploymentManifest::STATUS_PREPARED, $manifest->status);

        $manifest = $service->validateDeployment($manifest, $context);
        $this->assertSame(DeploymentManifest::STATUS_VALIDATED, $manifest->status);

        $manifest = $service->approveDeployment($manifest, $context);
        $this->assertSame(DeploymentManifest::STATUS_APPROVED, $manifest->status);

        $manifest = $service->markReady($manifest, $context);
        $this->assertSame(DeploymentManifest::STATUS_READY, $manifest->status);
    }

    public function test_lifecycle_steps_cannot_be_skipped(): void
    {
        $site = $this->createSite();
        $sitePackage = $this->activeSitePackage($site);
        [$service] = $this->service($site);
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $manifest = $service->prepareDeployment($sitePackage, $context);

        try {
            $service->approveDeployment($manifest, $context);
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('invalid_lifecycle_transition', $e->failureCode);
        }
    }
}
