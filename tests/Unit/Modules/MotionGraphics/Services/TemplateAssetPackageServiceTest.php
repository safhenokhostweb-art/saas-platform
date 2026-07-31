<?php

namespace Tests\Unit\Modules\MotionGraphics\Services;

use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\DTO\AuthorizationDecision;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Services\TemplateAssetPackageService;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use Tests\TestCase;

class TemplateAssetPackageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function tenantContext(bool $flagEnabled = true): TenantContextContract
    {
        $tenantContext = Mockery::mock(TenantContextContract::class);
        $tenantContext->shouldReceive('hasFeature')->with('motion_graphics_integration')->andReturn($flagEnabled);

        return $tenantContext;
    }

    private function allowingAuthorization(): MotionAuthorizationInterface
    {
        $authorization = Mockery::mock(MotionAuthorizationInterface::class);
        $authorization->shouldReceive('authorize')->andReturn(AuthorizationDecision::allow());

        return $authorization;
    }

    private function service(
        bool $flagEnabled,
        ?MotionAuthorizationInterface $authorization,
        MotionAssetReadContract $assets,
    ): TemplateAssetPackageService {
        return new TemplateAssetPackageService(
            $this->tenantContext($flagEnabled),
            Mockery::mock(MotionAuditEvents::class)->shouldIgnoreMissing(),
            $authorization ?? Mockery::mock(MotionAuthorizationInterface::class),
            $assets,
        );
    }

    public function test_it_resolves_the_published_version_when_none_is_given(): void
    {
        $assets = Mockery::mock(MotionAssetReadContract::class);
        $assets->shouldReceive('resolveActiveVersion')->with('motion-1')->andReturn(3);
        $assets->shouldReceive('findByCanonicalId')->with('motion-1', 3)->andReturn(
            new AssetReadModel('motion-1', 3, 'lottie', null, [], 'x', false)
        );

        $package = $this->service(true, $this->allowingAuthorization(), $assets)->createTemplateAssetPackage(
            'template-1',
            [['asset_id' => 'motion-1']],
            [],
            [],
            new MotionAdapterContext('user:1', 'platform', 'corr-1'),
        );

        $this->assertSame([['asset_id' => 'motion-1', 'version' => 3]], $package->required_assets);
        $this->assertSame(1, $package->package_version);
    }

    public function test_it_binds_an_explicit_version_when_valid(): void
    {
        $assets = Mockery::mock(MotionAssetReadContract::class);
        $assets->shouldReceive('findByCanonicalId')->with('motion-1', 2)->andReturn(
            new AssetReadModel('motion-1', 2, 'lottie', null, [], 'x', false)
        );

        $package = $this->service(true, $this->allowingAuthorization(), $assets)->createTemplateAssetPackage(
            'template-explicit',
            [['asset_id' => 'motion-1', 'version' => 2]],
            [],
            [],
            new MotionAdapterContext('user:1', 'platform', 'corr-1'),
        );

        $this->assertSame(2, $package->required_assets[0]['version']);
    }

    public function test_it_fails_closed_when_a_required_asset_cannot_be_resolved(): void
    {
        $assets = Mockery::mock(MotionAssetReadContract::class);
        $assets->shouldReceive('resolveActiveVersion')->with('motion-missing')->andReturnNull();

        try {
            $this->service(true, $this->allowingAuthorization(), $assets)->createTemplateAssetPackage(
                'template-missing',
                [['asset_id' => 'motion-missing']],
                [],
                [],
                new MotionAdapterContext('user:1', 'platform', 'corr-1'),
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('unresolved_required_asset', $e->failureCode);
        }
    }

    public function test_it_drops_an_unresolved_optional_asset_without_failing(): void
    {
        $assets = Mockery::mock(MotionAssetReadContract::class);
        $assets->shouldReceive('resolveActiveVersion')->with('motion-optional')->andReturnNull();

        $package = $this->service(true, $this->allowingAuthorization(), $assets)->createTemplateAssetPackage(
            'template-optional',
            [],
            [['asset_id' => 'motion-optional']],
            [],
            new MotionAdapterContext('user:1', 'platform', 'corr-1'),
        );

        $this->assertSame([], $package->optional_assets);
    }

    public function test_repeated_creation_for_the_same_template_increments_the_package_version(): void
    {
        $assets = Mockery::mock(MotionAssetReadContract::class);

        $service = $this->service(true, $this->allowingAuthorization(), $assets);
        $context = new MotionAdapterContext('user:1', 'platform', 'corr-1');

        $first = $service->createTemplateAssetPackage('template-versioned', [], [], [], $context);
        $second = $service->createTemplateAssetPackage('template-versioned', [], [], [], $context);

        $this->assertSame(1, $first->package_version);
        $this->assertSame(2, $second->package_version);
    }

    public function test_it_fails_closed_when_the_flag_is_disabled(): void
    {
        $this->expectException(MotionAdapterFailClosedException::class);

        $this->service(false, Mockery::mock(MotionAuthorizationInterface::class), Mockery::mock(MotionAssetReadContract::class))
            ->createTemplateAssetPackage('template-1', [], [], [], new MotionAdapterContext('user:1', 'platform', 'corr-1'));
    }

    public function test_it_fails_closed_when_authorization_denies(): void
    {
        $authorization = Mockery::mock(MotionAuthorizationInterface::class);
        $authorization->shouldReceive('authorize')->andReturn(AuthorizationDecision::deny('ability_denied', 'no'));

        try {
            $this->service(true, $authorization, Mockery::mock(MotionAssetReadContract::class))
                ->createTemplateAssetPackage('template-1', [], [], [], new MotionAdapterContext('user:1', 'platform', 'corr-1'));
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('ability_denied', $e->failureCode);
        }
    }
}
