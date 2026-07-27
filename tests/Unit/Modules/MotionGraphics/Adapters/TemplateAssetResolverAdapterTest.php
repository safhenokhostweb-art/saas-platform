<?php

namespace Tests\Unit\Modules\MotionGraphics\Adapters;

use App\Modules\MotionGraphics\Adapters\TemplateAssetResolverAdapter;
use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\DTO\AuthorizationDecision;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use Mockery;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\TemplateAssetResolutionReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\TemplateAssetReadContract;
use Tests\TestCase;

class TemplateAssetResolverAdapterTest extends TestCase
{
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

    public function test_resolve_required_assets_succeeds_when_every_required_reference_resolves(): void
    {
        $templateAssets = Mockery::mock(TemplateAssetReadContract::class);
        $templateAssets->shouldReceive('resolveReferences')
            ->with([['asset_id' => 'motion-1', 'version' => 1]])
            ->andReturn(new TemplateAssetResolutionReadModel([
                new AssetReadModel('motion-1', 1, 'lottie', null, [], 'x', false),
            ], []));
        $templateAssets->shouldReceive('resolveReferences')->with([])->andReturn(new TemplateAssetResolutionReadModel([], []));

        $adapter = new TemplateAssetResolverAdapter(
            $this->tenantContext(),
            Mockery::mock(MotionAuditEvents::class)->shouldIgnoreMissing(),
            $this->allowingAuthorization(),
            $templateAssets,
        );

        $dto = $adapter->resolveRequiredAssets(
            ['template_id' => 'template-1', 'required_assets' => [['asset_id' => 'motion-1', 'version' => 1, 'type' => 'lottie']]],
            [],
            new MotionAdapterContext('user:1', 'site-1', 'corr-1'),
        );

        $this->assertCount(1, $dto->requiredAssets);
    }

    public function test_resolve_required_assets_fails_closed_when_a_required_reference_cannot_be_resolved(): void
    {
        $templateAssets = Mockery::mock(TemplateAssetReadContract::class);
        $templateAssets->shouldReceive('resolveReferences')
            ->with([['asset_id' => 'motion-missing', 'version' => 1]])
            ->andReturn(new TemplateAssetResolutionReadModel([], ['motion-missing']));

        $adapter = new TemplateAssetResolverAdapter(
            $this->tenantContext(),
            Mockery::mock(MotionAuditEvents::class)->shouldIgnoreMissing(),
            $this->allowingAuthorization(),
            $templateAssets,
        );

        try {
            $adapter->resolveRequiredAssets(
                ['template_id' => 'template-1', 'required_assets' => [['asset_id' => 'motion-missing', 'version' => 1, 'type' => 'lottie']]],
                [],
                new MotionAdapterContext('user:1', 'site-1', 'corr-1'),
            );
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('unresolved_required_asset', $e->failureCode);
        }
    }

    public function test_resolve_required_assets_silently_drops_an_unresolved_optional_reference(): void
    {
        $templateAssets = Mockery::mock(TemplateAssetReadContract::class);
        $templateAssets->shouldReceive('resolveReferences')->with([])->andReturn(new TemplateAssetResolutionReadModel([], []));
        $templateAssets->shouldReceive('resolveReferences')
            ->with([['asset_id' => 'motion-optional', 'version' => 1]])
            ->andReturn(new TemplateAssetResolutionReadModel([], ['motion-optional']));

        $adapter = new TemplateAssetResolverAdapter(
            $this->tenantContext(),
            Mockery::mock(MotionAuditEvents::class)->shouldIgnoreMissing(),
            $this->allowingAuthorization(),
            $templateAssets,
        );

        $dto = $adapter->resolveRequiredAssets(
            ['template_id' => 'template-1', 'optional_assets' => [['asset_id' => 'motion-optional', 'version' => 1, 'type' => 'lottie']]],
            [],
            new MotionAdapterContext('user:1', 'site-1', 'corr-1'),
        );

        $this->assertSame([], $dto->optionalAssets);
    }

    public function test_create_package_definition_fails_closed_when_disabled(): void
    {
        $adapter = new TemplateAssetResolverAdapter(
            $this->tenantContext(false),
            Mockery::mock(MotionAuditEvents::class)->shouldIgnoreMissing(),
            Mockery::mock(MotionAuthorizationInterface::class),
            Mockery::mock(TemplateAssetReadContract::class),
        );

        $this->expectException(MotionAdapterFailClosedException::class);

        $adapter->createPackageDefinition(
            new \App\Modules\MotionGraphics\DTO\TemplateAssetRequestDTO('template-1'),
            new MotionAdapterContext('user:1', 'site-1', 'corr-1'),
        );
    }
}
