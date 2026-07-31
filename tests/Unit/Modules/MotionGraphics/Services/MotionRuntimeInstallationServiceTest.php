<?php

namespace Tests\Unit\Modules\MotionGraphics\Services;

use App\Models\Site;
use App\Modules\MotionGraphics\Contracts\MotionAssetResolverInterface;
use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\DTO\AuthorizationDecision;
use App\Modules\MotionGraphics\DTO\BrandVariantReference;
use App\Modules\MotionGraphics\DTO\CompatibilityDecision;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Models\AssetActivation;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
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
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionVersionReadContract;
use Tests\Support\CreatesMotionTestSite;
use Tests\TestCase;

class MotionRuntimeInstallationServiceTest extends TestCase
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

    private function projectionFor(string $assetId, int $version): RuntimeProjectionReadModel
    {
        return new RuntimeProjectionReadModel(
            assetReference: new AssetReadModel($assetId, $version, 'lottie', null, [], 'checksum-'.$version, false),
            publishedVersion: $version,
            optimizedReference: '/motion/x/v'.$version.'/a.json',
        );
    }

    /**
     * @return array{0: MotionRuntimeInstallationService, 1: MotionAssetReadContract, 2: MotionRuntimeProjectionReadContract}
     */
    private function service(
        Site $site,
        bool $flagEnabled = true,
        ?MotionAuthorizationInterface $authorization = null,
        ?MotionAssetReadContract $assets = null,
        ?MotionRuntimeProjectionReadContract $projections = null,
    ): array {
        $tenantContext = $this->tenantContext($site, $flagEnabled);
        $auditEvents = Mockery::mock(MotionAuditEvents::class)->shouldIgnoreMissing();
        $authorization ??= $this->allowingAuthorization();
        $assets ??= Mockery::mock(MotionAssetReadContract::class);
        $projections ??= Mockery::mock(MotionRuntimeProjectionReadContract::class);

        $templatePackages = new TemplateAssetPackageService($tenantContext, $auditEvents, $authorization, $assets);
        $activation = new SiteAssetPackageActivationService(
            $tenantContext, $auditEvents, $authorization, $this->compatibleResolver(), $assets,
            Mockery::mock(MotionVersionReadContract::class), $projections,
        );

        $installation = new MotionRuntimeInstallationService(
            $tenantContext, $auditEvents, $authorization, $templatePackages, $activation, $assets, $projections,
            new TemplateBuilderCompatibilityValidator(),
        );

        return [$installation, $assets, $projections];
    }

    private function stubAsset(MotionAssetReadContract $assets, string $assetId, int $version): void
    {
        $assets->shouldReceive('resolveActiveVersion')->with($assetId)->andReturn($version)->byDefault();
        $assets->shouldReceive('findByCanonicalId')->with($assetId, $version)->andReturn(
            new AssetReadModel($assetId, $version, 'lottie', null, [], 'checksum-'.$version, false)
        )->byDefault();
    }

    public function test_it_installs_a_template_and_generates_runtime_projections(): void
    {
        $site = $this->createSite();
        [$service, $assets, $projections] = $this->service($site);
        $this->stubAsset($assets, 'motion-1', 1);
        $projections->shouldReceive('projectAsset')->with('motion-1', (string) $site->id)->andReturn($this->projectionFor('motion-1', 1));

        $plan = $service->installTemplateForSite(
            'template-1', [['asset_id' => 'motion-1']], [], [], null,
            new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'),
        );

        $this->assertSame('active', $plan->sitePackage->activation_status);
        $this->assertCount(1, $plan->items);
        $this->assertSame('install', $plan->items[0]['activation_type']);
        $this->assertCount(1, $plan->projections);
        $this->assertFalse($plan->wasAlreadyUpToDate);
    }

    public function test_a_second_identical_installation_is_idempotent(): void
    {
        $site = $this->createSite();
        [$service, $assets, $projections] = $this->service($site);
        $this->stubAsset($assets, 'motion-1', 1);
        $projections->shouldReceive('projectAsset')->andReturn($this->projectionFor('motion-1', 1));
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $service->installTemplateForSite('template-1', [['asset_id' => 'motion-1']], [], [], null, $context);
        $second = $service->installTemplateForSite('template-1', [['asset_id' => 'motion-1']], [], [], null, $context);

        $this->assertTrue($second->wasAlreadyUpToDate);
        $this->assertSame(1, TemplateAssetPackage::query()->count());
        $this->assertSame(1, AssetActivation::query()->count());
    }

    public function test_a_changed_required_version_creates_a_new_package_and_an_upgrade_activation(): void
    {
        $site = $this->createSite();
        [$service, $assets, $projections] = $this->service($site);
        $assets->shouldReceive('resolveActiveVersion')->with('motion-1')->andReturn(1, 2);
        $assets->shouldReceive('findByCanonicalId')->with('motion-1', 1)->andReturn(new AssetReadModel('motion-1', 1, 'lottie', null, [], 'c1', false));
        $assets->shouldReceive('findByCanonicalId')->with('motion-1', 2)->andReturn(new AssetReadModel('motion-1', 2, 'lottie', null, [], 'c2', false));
        $projections->shouldReceive('projectAsset')->andReturn($this->projectionFor('motion-1', 1), $this->projectionFor('motion-1', 2));
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $service->installTemplateForSite('template-1', [['asset_id' => 'motion-1']], [], [], null, $context);
        $second = $service->installTemplateForSite('template-1', [['asset_id' => 'motion-1']], [], [], null, $context);

        $this->assertFalse($second->wasAlreadyUpToDate);
        $this->assertSame(2, TemplateAssetPackage::query()->count());
        $this->assertSame(2, AssetActivation::query()->count());
        $this->assertSame('upgrade', $second->items[0]['activation_type']);
    }

    public function test_it_fails_closed_on_an_unresolved_required_asset(): void
    {
        $site = $this->createSite();
        [$service, $assets] = $this->service($site);
        $assets->shouldReceive('resolveActiveVersion')->with('motion-missing')->andReturnNull();

        try {
            $service->installTemplateForSite(
                'template-1', [['asset_id' => 'motion-missing']], [], [], null,
                new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'),
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('unresolved_required_asset', $e->failureCode);
        }

        $this->assertSame(0, TemplateAssetPackage::query()->count());
    }

    public function test_it_rejects_incompatible_assets_via_the_richer_template_builder_validator(): void
    {
        $site = $this->createSite();
        [$service, $assets] = $this->service($site);
        $assets->shouldReceive('resolveActiveVersion')->with('motion-1')->andReturn(1);
        $assets->shouldReceive('findByCanonicalId')->with('motion-1', 1)->andReturn(
            new AssetReadModel('motion-1', 1, 'svg', null, [], 'c1', false) // type: svg
        );

        try {
            $service->installTemplateForSite(
                'template-1', [['asset_id' => 'motion-1']], [], ['asset_type' => 'lottie'], null,
                new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'),
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('compatibility_rejected', $e->failureCode);
        }

        $this->assertSame(0, TemplateAssetPackage::query()->count());
    }

    public function test_it_rejects_an_unapproved_brand_variant(): void
    {
        $site = $this->createSite();
        [$service] = $this->service($site);
        $variant = new BrandVariantReference('variant-1', 'motion-1', 1, (string) $site->id, BrandVariantReference::STATUS_PENDING);

        try {
            $service->installTemplateForSite(
                'template-1', [], [], [], $variant,
                new MotionAdapterContext('user:1', (string) $site->id, 'corr-1'),
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('brand_variant_not_approved', $e->failureCode);
        }
    }

    public function test_it_fails_closed_when_the_flag_is_disabled(): void
    {
        $site = $this->createSite();
        [$service] = $this->service($site, false);

        $this->expectException(MotionAdapterFailClosedException::class);

        $service->installTemplateForSite(
            'template-1', [], [], [], null, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1')
        );
    }

    public function test_it_fails_closed_when_authorization_denies(): void
    {
        $site = $this->createSite();
        $authorization = Mockery::mock(MotionAuthorizationInterface::class);
        $authorization->shouldReceive('authorize')->andReturn(AuthorizationDecision::deny('ability_denied', 'Customers cannot create packages.'));
        [$service] = $this->service($site, true, $authorization);

        try {
            $service->installTemplateForSite(
                'template-1', [], [], [], null, new MotionAdapterContext('user:1', (string) $site->id, 'corr-1')
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('ability_denied', $e->failureCode);
        }

        $this->assertSame(0, TemplateAssetPackage::query()->count());
    }

    public function test_project_site_package_omits_an_item_it_cannot_currently_project(): void
    {
        $site = $this->createSite();
        [$service, $assets, $projections] = $this->service($site);
        $this->stubAsset($assets, 'motion-1', 1);
        $projections->shouldReceive('projectAsset')->with('motion-1', (string) $site->id)->andReturn($this->projectionFor('motion-1', 1));
        $context = new MotionAdapterContext('user:1', (string) $site->id, 'corr-1');

        $plan = $service->installTemplateForSite('template-1', [['asset_id' => 'motion-1']], [], [], null, $context);

        // A second, independent projections mock simulating the asset
        // becoming unavailable after installation (e.g. retired).
        $laterProjections = Mockery::mock(MotionRuntimeProjectionReadContract::class);
        $laterProjections->shouldReceive('projectAsset')->andReturnNull();
        [$laterService] = $this->service($site, true, null, Mockery::mock(MotionAssetReadContract::class), $laterProjections);

        $result = $laterService->projectSitePackage($plan->sitePackage, $context);

        $this->assertSame([], $result);
    }
}
