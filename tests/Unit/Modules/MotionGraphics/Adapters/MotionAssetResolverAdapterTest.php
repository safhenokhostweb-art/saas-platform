<?php

namespace Tests\Unit\Modules\MotionGraphics\Adapters;

use App\Modules\MotionGraphics\Adapters\MotionAssetResolverAdapter;
use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\DTO\AuthorizationDecision;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\DTO\MotionAssetReferenceDTO;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use Mockery;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionAssetReadContract;
use Tests\TestCase;

class MotionAssetResolverAdapterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function tenantContext(bool $flagEnabled): TenantContextContract
    {
        $tenantContext = Mockery::mock(TenantContextContract::class);
        $tenantContext->shouldReceive('hasFeature')->with('motion_graphics_integration')->andReturn($flagEnabled);

        return $tenantContext;
    }

    private function adapter(
        bool $flagEnabled,
        ?MotionAuthorizationInterface $authorization = null,
        ?MotionAssetReadContract $assets = null,
    ): MotionAssetResolverAdapter {
        return new MotionAssetResolverAdapter(
            $this->tenantContext($flagEnabled),
            Mockery::mock(MotionAuditEvents::class)->shouldIgnoreMissing(),
            $authorization ?? Mockery::mock(MotionAuthorizationInterface::class),
            $assets ?? Mockery::mock(MotionAssetReadContract::class),
        );
    }

    public function test_resolve_canonical_fails_closed_when_the_flag_is_disabled(): void
    {
        $adapter = $this->adapter(false);

        $this->expectException(MotionAdapterFailClosedException::class);

        $adapter->resolveCanonical('motion-1', 1, new MotionAdapterContext('user:1', 'site-1', 'corr-1'));
    }

    public function test_resolve_canonical_fails_closed_when_authorization_is_denied(): void
    {
        $authorization = Mockery::mock(MotionAuthorizationInterface::class);
        $authorization->shouldReceive('authorize')
            ->with('user:1', 'motion.view', 'motion-1', 'site-1')
            ->andReturn(AuthorizationDecision::deny('tenant_context_mismatch', 'no'));

        $adapter = $this->adapter(true, $authorization);

        try {
            $adapter->resolveCanonical('motion-1', 1, new MotionAdapterContext('user:1', 'site-1', 'corr-1'));
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('tenant_context_mismatch', $e->failureCode);
        }
    }

    public function test_resolve_canonical_fails_closed_when_the_module_read_contract_finds_nothing(): void
    {
        $authorization = Mockery::mock(MotionAuthorizationInterface::class);
        $authorization->shouldReceive('authorize')->andReturn(AuthorizationDecision::allow());

        $assets = Mockery::mock(MotionAssetReadContract::class);
        $assets->shouldReceive('findByCanonicalId')->with('motion-1', 1)->andReturnNull();

        $adapter = $this->adapter(true, $authorization, $assets);

        try {
            $adapter->resolveCanonical('motion-1', 1, new MotionAdapterContext('user:1', 'site-1', 'corr-1'));
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('asset_not_available', $e->failureCode);
        }
    }

    public function test_resolve_canonical_maps_the_real_read_model_into_a_dto_once_authorized(): void
    {
        $authorization = Mockery::mock(MotionAuthorizationInterface::class);
        $authorization->shouldReceive('authorize')->andReturn(AuthorizationDecision::allow());

        $assets = Mockery::mock(MotionAssetReadContract::class);
        $assets->shouldReceive('findByCanonicalId')->with('motion-1', 1)->andReturn(
            new AssetReadModel('motion-1', 1, 'lottie', 'finance', ['engine' => 'lottie'], 'abc123', true)
        );

        $auditEvents = Mockery::mock(MotionAuditEvents::class);
        $auditEvents->shouldReceive('record')->once()->with(MotionAuditEvents::ASSET_RESOLVED, Mockery::type('array'));

        $adapter = new MotionAssetResolverAdapter($this->tenantContext(true), $auditEvents, $authorization, $assets);

        $dto = $adapter->resolveCanonical('motion-1', 1, new MotionAdapterContext('user:1', 'site-1', 'corr-1'));

        $this->assertSame('motion-1', $dto->assetId);
        $this->assertSame(1, $dto->version);
        $this->assertSame('lottie', $dto->type);
        $this->assertSame('finance', $dto->compatibility['category']);
        $this->assertSame('abc123', $dto->compatibility['checksum']);
        $this->assertTrue($dto->compatibility['theme_support']);
    }

    public function test_validate_compatibility_matches_every_requested_dimension(): void
    {
        $adapter = $this->adapter(true);
        $asset = new MotionAssetReferenceDTO('motion-1', 1, 'lottie', ['industry' => 'finance', 'style' => 'modern']);

        $decision = $adapter->validateCompatibility($asset, ['industry' => 'finance'], []);

        $this->assertTrue($decision->compatible);
    }

    public function test_validate_compatibility_rejects_a_mismatched_dimension(): void
    {
        $adapter = $this->adapter(true);
        $asset = new MotionAssetReferenceDTO('motion-1', 1, 'lottie', ['industry' => 'finance']);

        $decision = $adapter->validateCompatibility($asset, ['industry' => 'retail'], []);

        $this->assertFalse($decision->compatible);
        $this->assertSame('compatibility_mismatch', $decision->code);
    }
}
