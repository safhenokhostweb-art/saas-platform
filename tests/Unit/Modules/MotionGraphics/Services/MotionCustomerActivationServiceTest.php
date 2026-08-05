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
use App\Modules\MotionGraphics\Services\MotionCustomerActivationService;
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

class MotionCustomerActivationServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function realTenantContext(): TenantContextContract
    {
        return $this->app->make(TenantContextContract::class);
    }

    private function activePackage(Site $site, string $assetId = 'motion-1'): SiteAssetPackage
    {
        $templatePackage = TemplateAssetPackage::query()->create([
            'template_reference' => 'template-1',
            'required_assets' => [['asset_id' => $assetId, 'version' => 1]],
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
            'asset_id' => $assetId, 'active_version' => 1,
            'bound_checksum' => 'checksum-1', 'installed_at' => now(),
        ]);

        return $sitePackage->fresh(['items', 'templateAssetPackage']);
    }

    private function activationService(bool $flagEnabled = true, bool $licenseAllows = true): MotionCustomerActivationService
    {
        $tenantContext = Mockery::mock(TenantContextContract::class);
        $tenantContext->shouldReceive('hasFeature')->andReturn($flagEnabled);
        $tenantContext->shouldReceive('site')->andReturnUsing(fn () => $this->currentSite);
        $tenantContext->shouldReceive('set')->andReturnUsing(function ($site) {
            $this->currentSite = $site;
        });

        $auditEvents = Mockery::mock(MotionAuditEvents::class)->shouldIgnoreMissing();
        $authorization = Mockery::mock(MotionAuthorizationInterface::class);
        $authorization->shouldReceive('authorize')->andReturn(AuthorizationDecision::allow());

        $assets = Mockery::mock(MotionAssetReadContract::class);
        $assets->shouldReceive('resolveActiveVersion')->andReturn(1)->byDefault();
        $assets->shouldReceive('findByCanonicalId')->andReturn(
            new AssetReadModel('motion-1', 1, 'lottie', null, [], 'checksum-1', false)
        )->byDefault();

        $projections = Mockery::mock(MotionRuntimeProjectionReadContract::class);
        $projections->shouldReceive('projectAsset')->andReturn(
            new RuntimeProjectionReadModel(
                assetReference: new AssetReadModel('motion-1', 1, 'lottie', null, [], 'checksum-1', false),
                publishedVersion: 1,
                optimizedReference: '/motion/motion-1/v1/output.json',
            )
        )->byDefault();

        // MotionCustomerRuntimeService deliberately bypasses
        // MotionAuthorizationInterface (see its own docblock — the
        // tenant.api machine-to-machine channel has no Auth::user()), so
        // only the read contracts and license gateway are needed here;
        // activePackage() below builds its fixtures directly rather than
        // through SiteAssetPackageActivationService/MotionRuntimeInstallationService.
        $runtimeService = new MotionCustomerRuntimeService(
            $tenantContext, $auditEvents, new FakeMotionLicenseGateway(allow: $licenseAllows), $assets, $projections,
        );

        return new MotionCustomerActivationService($tenantContext, $auditEvents, $runtimeService);
    }

    private ?Site $currentSite = null;

    public function test_enable_turns_on_the_site_flag(): void
    {
        $site = $this->createSite();
        $this->activePackage($site);
        $service = $this->activationService();

        $manifest = $service->enable($site, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'));

        $this->assertTrue((bool) $site->fresh()->features['motion_graphics_integration']);
        $this->assertNotEmpty($manifest->manifestChecksum);
    }

    /**
     * Phase 9 (docs/motion-commercialization/ENTITLEMENT_RULES.md):
     * replaces Phase 8's artificial "only one site, ever" cap. Two
     * separately-entitled sites can each be enabled independently — real
     * per-site entitlement (proven in the Feature-level
     * MotionCommercializationTest against the REAL RealMotionLicenseGateway)
     * is the actual gate now; this fake-gateway-based unit test only
     * proves the *cap itself* is gone, not the real entitlement check
     * (which this file's fake gateway bypasses by design).
     */
    public function test_enable_no_longer_caps_at_a_single_site(): void
    {
        $siteA = $this->createSite('Site A');
        $this->activePackage($siteA, 'motion-1');
        $siteB = $this->createSite('Site B');
        $this->activePackage($siteB, 'motion-1');
        $service = $this->activationService();

        $service->enable($siteA, new MotionAdapterContext('user:1', (string) $siteA->id, 'corr-1'));
        $service->enable($siteB, new MotionAdapterContext('user:1', (string) $siteB->id, 'corr-2'));

        $this->assertTrue((bool) $siteA->fresh()->features['motion_graphics_integration']);
        $this->assertTrue((bool) $siteB->fresh()->features['motion_graphics_integration']);
    }

    public function test_disable_turns_off_the_site_flag(): void
    {
        $site = $this->createSite();
        $site->features = ['motion_graphics_integration' => true];
        $site->save();
        $service = $this->activationService();

        $service->disable($site, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'), 'test rollback');

        $this->assertFalse((bool) $site->fresh()->features['motion_graphics_integration']);
    }

    public function test_disable_preserves_other_feature_keys(): void
    {
        $site = $this->createSite();
        $site->features = ['motion_graphics_integration' => true, 'some_other_flag' => true];
        $site->save();
        $service = $this->activationService();

        $service->disable($site, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'), 'test rollback');

        $fresh = $site->fresh();
        $this->assertFalse((bool) $fresh->features['motion_graphics_integration']);
        $this->assertTrue((bool) $fresh->features['some_other_flag']);
    }

    public function test_health_check_reports_healthy_for_a_valid_active_package(): void
    {
        $site = $this->createSite();
        $this->activePackage($site);
        $service = $this->activationService();

        $result = $service->healthCheck($site, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'));

        $this->assertTrue($result['healthy']);
        $this->assertSame(1, $result['asset_count']);
    }

    public function test_health_check_reports_unhealthy_when_no_package_is_active(): void
    {
        $site = $this->createSite();
        $service = $this->activationService();

        $result = $service->healthCheck($site, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'));

        $this->assertFalse($result['healthy']);
        $this->assertSame('no_active_package', $result['reason']);
    }

    public function test_prepare_refuses_an_inactive_site(): void
    {
        $site = $this->createSite();
        $site->is_active = false;
        $site->save();
        $service = $this->activationService();

        try {
            $service->prepare($site, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'));
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('site_inactive', $e->failureCode);
        }
    }

    /**
     * Rollback rehearsal (ROLLBACK_PLAN_AND_REHEARSAL.md): enable, disable
     * (rollback), then enable again for the *same* site — the exact
     * sequence a real re-activation after a rollback would follow.
     */
    public function test_a_site_can_be_enabled_rolled_back_and_re_enabled(): void
    {
        $site = $this->createSite();
        $this->activePackage($site);
        $service = $this->activationService();
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $service->enable($site, $context);
        $this->assertTrue((bool) $site->fresh()->features['motion_graphics_integration']);

        $service->disable($site, $context, 'rollback rehearsal');
        $this->assertFalse((bool) $site->fresh()->features['motion_graphics_integration']);

        $service->enable($site, $context);
        $this->assertTrue((bool) $site->fresh()->features['motion_graphics_integration']);
    }
}
