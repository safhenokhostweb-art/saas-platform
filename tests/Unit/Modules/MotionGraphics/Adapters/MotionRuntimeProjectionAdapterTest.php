<?php

namespace Tests\Unit\Modules\MotionGraphics\Adapters;

use App\Modules\MotionGraphics\Adapters\MotionRuntimeProjectionAdapter;
use App\Modules\MotionGraphics\Contracts\MotionAuthorizationInterface;
use App\Modules\MotionGraphics\DTO\AuthorizationDecision;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Exceptions\MotionAdapterFailClosedException;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use Mockery;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\AssetReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\Model\RuntimeProjectionReadModel;
use SaaSPlatform\Modules\MotionGraphics\Contracts\Read\MotionRuntimeProjectionReadContract;
use Tests\TestCase;

class MotionRuntimeProjectionAdapterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function adapter(
        ?string $trustedSiteId,
        MotionAuthorizationInterface $authorization,
        MotionRuntimeProjectionReadContract $projections,
        ?MotionAuditEvents $auditEvents = null,
    ): MotionRuntimeProjectionAdapter {
        $tenantContext = Mockery::mock(TenantContextContract::class);
        $tenantContext->shouldReceive('hasFeature')->with('motion_graphics_integration')->andReturn(true);
        $tenantContext->shouldReceive('site')->andReturn($trustedSiteId === null ? null : (object) ['id' => $trustedSiteId]);

        return new MotionRuntimeProjectionAdapter(
            $tenantContext,
            $auditEvents ?? Mockery::mock(MotionAuditEvents::class)->shouldIgnoreMissing(),
            $authorization,
            $projections,
        );
    }

    public function test_project_published_maps_a_real_projection_into_a_dto(): void
    {
        $authorization = Mockery::mock(MotionAuthorizationInterface::class);
        $authorization->shouldReceive('authorize')->andReturn(AuthorizationDecision::allow());

        $projections = Mockery::mock(MotionRuntimeProjectionReadContract::class);
        $projections->shouldReceive('projectAsset')->with('motion-1', 'site-1')->andReturn(new RuntimeProjectionReadModel(
            assetReference: new AssetReadModel('motion-1', 5, 'lottie', null, [], 'x', false),
            publishedVersion: 5,
            optimizedReference: '/motion/x/v5/a.json',
            themeTokens: [],
            placementRules: [],
        ));

        $auditEvents = Mockery::mock(MotionAuditEvents::class);
        $auditEvents->shouldReceive('record')->once()->with(MotionAuditEvents::RUNTIME_REQUESTED, Mockery::type('array'));

        $adapter = $this->adapter('site-1', $authorization, $projections, $auditEvents);

        $dto = $adapter->projectPublished('motion-1', 5, new MotionAdapterContext('user:1', 'site-1', 'corr-1'));

        $this->assertSame(5, $dto->publishedVersion);
        $this->assertSame('/motion/x/v5/a.json', $dto->optimizedReference);
    }

    public function test_project_published_fails_closed_when_the_requested_version_does_not_match_the_published_one(): void
    {
        $authorization = Mockery::mock(MotionAuthorizationInterface::class);
        $authorization->shouldReceive('authorize')->andReturn(AuthorizationDecision::allow());

        $projections = Mockery::mock(MotionRuntimeProjectionReadContract::class);
        $projections->shouldReceive('projectAsset')->andReturn(new RuntimeProjectionReadModel(
            assetReference: new AssetReadModel('motion-1', 5, 'lottie', null, [], 'x', false),
            publishedVersion: 5,
            optimizedReference: '/motion/x/v5/a.json',
        ));

        $adapter = $this->adapter('site-1', $authorization, $projections);

        try {
            $adapter->projectPublished('motion-1', 3, new MotionAdapterContext('user:1', 'site-1', 'corr-1'));
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('projection_not_available', $e->failureCode);
        }
    }

    public function test_project_published_fails_closed_when_no_projection_is_available(): void
    {
        $authorization = Mockery::mock(MotionAuthorizationInterface::class);
        $authorization->shouldReceive('authorize')->andReturn(AuthorizationDecision::allow());

        $projections = Mockery::mock(MotionRuntimeProjectionReadContract::class);
        $projections->shouldReceive('projectAsset')->andReturnNull();

        $adapter = $this->adapter('site-1', $authorization, $projections);

        try {
            $adapter->projectPublished('motion-1', 1, new MotionAdapterContext('user:1', 'site-1', 'corr-1'));
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('projection_not_available', $e->failureCode);
        }
    }

    public function test_project_published_fails_closed_on_tenant_mismatch_before_touching_the_read_contract(): void
    {
        $authorization = Mockery::mock(MotionAuthorizationInterface::class);
        $authorization->shouldReceive('authorize')->andReturn(AuthorizationDecision::allow());

        // The read contract must never be called when the tenant context
        // does not match — asserted by never() below.
        $projections = Mockery::mock(MotionRuntimeProjectionReadContract::class);
        $projections->shouldNotReceive('projectAsset');

        $adapter = $this->adapter('site-1', $authorization, $projections);

        try {
            $adapter->projectPublished('motion-1', 1, new MotionAdapterContext('user:1', 'site-2', 'corr-1'));
            $this->fail('Expected a fail-closed exception.');
        } catch (MotionAdapterFailClosedException $e) {
            $this->assertSame('tenant_context_mismatch', $e->failureCode);
        }
    }
}
