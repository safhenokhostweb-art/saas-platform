<?php

namespace Tests\Unit\Modules\MotionGraphics\Services;

use App\Modules\MotionGraphics\Contracts\MotionAssetResolverInterface;
use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\DTO\AuthorizationDecision;
use App\Modules\MotionGraphics\DTO\CompatibilityDecision;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\AssetActivation;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use App\Modules\MotionGraphics\Services\SiteAssetPackageActivationService;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\RuntimeProjectionReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\VersionReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionVersionReadContract;
use Tests\Support\CreatesMotionTestSite;
use Tests\TestCase;

class SiteAssetPackageActivationServiceTest extends TestCase
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

    private function compatibleResolver(): MotionAssetResolverInterface
    {
        $resolver = Mockery::mock(MotionAssetResolverInterface::class);
        $resolver->shouldReceive('validateCompatibility')->andReturn(CompatibilityDecision::compatible());

        return $resolver;
    }

    private function templatePackage(array $requiredAssets = [], array $compatibilityRules = []): TemplateAssetPackage
    {
        return TemplateAssetPackage::query()->create([
            'template_reference' => 'template-1',
            'required_assets' => $requiredAssets ?: [['asset_id' => 'motion-1', 'version' => 1]],
            'compatibility_rules' => $compatibilityRules,
            'package_version' => 1,
        ]);
    }

    private function service(
        Site $site,
        bool $flagEnabled,
        MotionAuthorizationInterface $authorization,
        MotionAssetResolverInterface $assetResolver,
        MotionAssetReadContract $assets,
        MotionVersionReadContract $versions,
        MotionRuntimeProjectionReadContract $projections,
    ): SiteAssetPackageActivationService {
        return new SiteAssetPackageActivationService(
            $this->tenantContext($site, $flagEnabled),
            Mockery::mock(MotionAuditEvents::class)->shouldIgnoreMissing(),
            $authorization,
            $assetResolver,
            $assets,
            $versions,
            $projections,
        );
    }

    private function projectionFor(string $assetId, int $version): RuntimeProjectionReadModel
    {
        return new RuntimeProjectionReadModel(
            assetReference: new AssetReadModel($assetId, $version, 'lottie', null, [], 'checksum-'.$version, false),
            publishedVersion: $version,
            optimizedReference: '/motion/x/v'.$version.'/a.json',
        );
    }

    public function test_activate_creates_the_site_package_item_and_snapshot(): void
    {
        $site = $this->createSite();
        $templatePackage = $this->templatePackage();

        $projections = Mockery::mock(MotionRuntimeProjectionReadContract::class);
        $projections->shouldReceive('projectAsset')->with('motion-1', (string) $site->id)->andReturn($this->projectionFor('motion-1', 1));

        $sitePackage = $this->service(
            $site, true, $this->allowingAuthorization(), $this->compatibleResolver(),
            Mockery::mock(MotionAssetReadContract::class), Mockery::mock(MotionVersionReadContract::class), $projections,
        )->activate($templatePackage, 'brand-a', new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'));

        $this->assertSame(SiteAssetPackage::STATUS_ACTIVE, $sitePackage->activation_status);
        $this->assertCount(1, $sitePackage->items);
        $this->assertSame(1, $sitePackage->items->first()->active_version);
        $this->assertCount(1, $sitePackage->snapshots);
        $this->assertSame(1, AssetActivation::query()->count());
        $this->assertSame(AssetActivation::TYPE_INSTALL, AssetActivation::query()->first()->activation_type);
    }

    public function test_activate_fails_closed_when_the_asset_is_not_published_or_not_owned(): void
    {
        $site = $this->createSite();
        $templatePackage = $this->templatePackage();

        $projections = Mockery::mock(MotionRuntimeProjectionReadContract::class);
        $projections->shouldReceive('projectAsset')->andReturnNull();

        try {
            $this->service(
                $site, true, $this->allowingAuthorization(), $this->compatibleResolver(),
                Mockery::mock(MotionAssetReadContract::class), Mockery::mock(MotionVersionReadContract::class), $projections,
            )->activate($templatePackage, null, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'));
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('asset_not_available_for_site', $e->failureCode);
        }

        $this->assertSame(0, SiteAssetPackage::query()->count());
    }

    public function test_activate_fails_closed_when_compatibility_is_rejected(): void
    {
        $site = $this->createSite();
        $templatePackage = $this->templatePackage(compatibilityRules: ['industry' => 'finance']);

        $projections = Mockery::mock(MotionRuntimeProjectionReadContract::class);
        $projections->shouldReceive('projectAsset')->andReturn($this->projectionFor('motion-1', 1));

        $resolver = Mockery::mock(MotionAssetResolverInterface::class);
        $resolver->shouldReceive('validateCompatibility')->andReturn(CompatibilityDecision::incompatible('compatibility_mismatch', 'no'));

        try {
            $this->service(
                $site, true, $this->allowingAuthorization(), $resolver,
                Mockery::mock(MotionAssetReadContract::class), Mockery::mock(MotionVersionReadContract::class), $projections,
            )->activate($templatePackage, null, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'));
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('compatibility_rejected', $e->failureCode);
        }

        $this->assertSame(0, SiteAssetPackage::query()->count());
    }

    public function test_reactivating_with_the_same_version_does_not_create_a_new_activation_record(): void
    {
        $site = $this->createSite();
        $templatePackage = $this->templatePackage();

        $projections = Mockery::mock(MotionRuntimeProjectionReadContract::class);
        $projections->shouldReceive('projectAsset')->andReturn($this->projectionFor('motion-1', 1));

        $service = $this->service(
            $site, true, $this->allowingAuthorization(), $this->compatibleResolver(),
            Mockery::mock(MotionAssetReadContract::class), Mockery::mock(MotionVersionReadContract::class), $projections,
        );
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $service->activate($templatePackage, null, $context);
        $service->activate($templatePackage, null, $context);

        $this->assertSame(1, AssetActivation::query()->count());
    }

    public function test_reactivating_with_a_different_version_creates_an_upgrade_activation(): void
    {
        $site = $this->createSite();
        $templatePackage = $this->templatePackage();

        $projections = Mockery::mock(MotionRuntimeProjectionReadContract::class);
        $projections->shouldReceive('projectAsset')->andReturn($this->projectionFor('motion-1', 1), $this->projectionFor('motion-1', 2));

        $service = $this->service(
            $site, true, $this->allowingAuthorization(), $this->compatibleResolver(),
            Mockery::mock(MotionAssetReadContract::class), Mockery::mock(MotionVersionReadContract::class), $projections,
        );
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $service->activate($templatePackage, null, $context);

        // A newer frozen package for the SAME template_reference — activating
        // it for the same site upgrades the existing SiteAssetPackage's
        // pointer in place (see findOrCreateSitePackage) rather than creating
        // a second package row, and re-binds the changed item as an upgrade.
        $templatePackageV2 = TemplateAssetPackage::query()->create([
            'template_reference' => 'template-1', 'required_assets' => [['asset_id' => 'motion-1', 'version' => 2]], 'package_version' => 2,
        ]);
        $sitePackage = $service->activate($templatePackageV2, null, $context);

        $this->assertSame(1, SiteAssetPackage::query()->count());
        $this->assertSame($templatePackageV2->id, $sitePackage->template_asset_package_id);
        $this->assertSame(2, $sitePackage->items->first()->active_version);
        $this->assertSame(2, AssetActivation::query()->count());
        $this->assertSame(AssetActivation::TYPE_UPGRADE, AssetActivation::query()->latest('id')->first()->activation_type);
    }

    public function test_activate_rejects_a_conflicting_brand_variant_on_an_existing_package(): void
    {
        $site = $this->createSite();
        $templatePackage = $this->templatePackage();

        $projections = Mockery::mock(MotionRuntimeProjectionReadContract::class);
        $projections->shouldReceive('projectAsset')->andReturn($this->projectionFor('motion-1', 1));

        $service = $this->service(
            $site, true, $this->allowingAuthorization(), $this->compatibleResolver(),
            Mockery::mock(MotionAssetReadContract::class), Mockery::mock(MotionVersionReadContract::class), $projections,
        );
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $service->activate($templatePackage, 'brand-a', $context);

        try {
            $service->activate($templatePackage, 'brand-b', $context);
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('brand_variant_conflict', $e->failureCode);
        }
    }

    public function test_rollback_creates_a_new_activation_and_never_mutates_history(): void
    {
        $site = $this->createSite();
        $templatePackage = $this->templatePackage([['asset_id' => 'motion-1', 'version' => 3]]);

        $projections = Mockery::mock(MotionRuntimeProjectionReadContract::class);
        $projections->shouldReceive('projectAsset')->andReturn($this->projectionFor('motion-1', 3));

        $activateService = $this->service(
            $site, true, $this->allowingAuthorization(), $this->compatibleResolver(),
            Mockery::mock(MotionAssetReadContract::class), Mockery::mock(MotionVersionReadContract::class), $projections,
        );
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');
        $activateService->activate($templatePackage, null, $context);

        $item = SiteAssetPackageItem::query()->first();
        $firstActivationId = AssetActivation::query()->first()->id;

        $versions = Mockery::mock(MotionVersionReadContract::class);
        $versions->shouldReceive('resolveVersion')->with('motion-1', 2)->andReturn(new VersionReadModel('motion-1', 2, 'published', 1, false));
        $assets = Mockery::mock(MotionAssetReadContract::class);
        $assets->shouldReceive('findByCanonicalId')->with('motion-1', 2)->andReturn(new AssetReadModel('motion-1', 2, 'lottie', null, [], 'checksum-2', false));

        $rollbackService = $this->service(
            $site, true, $this->allowingAuthorization(), $this->compatibleResolver(),
            $assets, $versions, Mockery::mock(MotionRuntimeProjectionReadContract::class),
        );
        $activation = $rollbackService->rollback($item, 2, $context);

        $this->assertSame(2, $activation->version);
        $this->assertSame(3, $activation->previous_version);
        $this->assertSame(AssetActivation::TYPE_ROLLBACK, $activation->activation_type);
        $this->assertSame(2, $item->fresh()->active_version);

        // The original install activation is untouched.
        $original = AssetActivation::query()->find($firstActivationId);
        $this->assertSame(3, $original->version);
        $this->assertSame(2, AssetActivation::query()->count());
    }

    public function test_rollback_fails_closed_when_the_target_version_is_not_available(): void
    {
        $site = $this->createSite();
        $templatePackage = $this->templatePackage([['asset_id' => 'motion-1', 'version' => 3]]);

        $projections = Mockery::mock(MotionRuntimeProjectionReadContract::class);
        $projections->shouldReceive('projectAsset')->andReturn($this->projectionFor('motion-1', 3));

        $activateService = $this->service(
            $site, true, $this->allowingAuthorization(), $this->compatibleResolver(),
            Mockery::mock(MotionAssetReadContract::class), Mockery::mock(MotionVersionReadContract::class), $projections,
        );
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');
        $activateService->activate($templatePackage, null, $context);
        $item = SiteAssetPackageItem::query()->first();

        $versions = Mockery::mock(MotionVersionReadContract::class);
        $versions->shouldReceive('resolveVersion')->andReturnNull();

        $rollbackService = $this->service(
            $site, true, $this->allowingAuthorization(), $this->compatibleResolver(),
            Mockery::mock(MotionAssetReadContract::class), $versions, Mockery::mock(MotionRuntimeProjectionReadContract::class),
        );

        try {
            $rollbackService->rollback($item, 1, $context);
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('version_not_available', $e->failureCode);
        }

        $this->assertSame(3, $item->fresh()->active_version);
    }

    public function test_rollback_denies_cross_tenant_access(): void
    {
        $siteA = $this->createSite('Site A');
        $siteB = $this->createSite('Site B');
        $templatePackage = $this->templatePackage();

        $projections = Mockery::mock(MotionRuntimeProjectionReadContract::class);
        $projections->shouldReceive('projectAsset')->andReturn($this->projectionFor('motion-1', 1));

        $activateService = $this->service(
            $siteA, true, $this->allowingAuthorization(), $this->compatibleResolver(),
            Mockery::mock(MotionAssetReadContract::class), Mockery::mock(MotionVersionReadContract::class), $projections,
        );
        $activateService->activate($templatePackage, null, new MotionAdapterContext('user:1', (string) $siteA->id, 'corr-1'));
        $item = SiteAssetPackageItem::query()->first();

        // Site B's own trusted tenant context, attempting to roll back Site A's item.
        $rollbackService = $this->service(
            $siteB, true, $this->allowingAuthorization(), $this->compatibleResolver(),
            Mockery::mock(MotionAssetReadContract::class), Mockery::mock(MotionVersionReadContract::class), Mockery::mock(MotionRuntimeProjectionReadContract::class),
        );

        try {
            $rollbackService->rollback($item, 1, new MotionAdapterContext('user:2', (string) $siteB->id, 'corr-1'));
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('tenant_isolation_violation', $e->failureCode);
        }
    }
}
