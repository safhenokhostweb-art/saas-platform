<?php

namespace Tests\Unit\Modules\MotionGraphics\Adapters;

use App\Modules\MotionGraphics\Adapters\MotionAuthorizationAdapter;
use App\Modules\MotionGraphics\DTO\MotionAdapterContext;
use App\Modules\MotionGraphics\Support\MotionAuditEvents;
use Illuminate\Support\Facades\Auth;
use Mockery;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\MotionPermissionGatewayContract;
use SaaSPlatform\Modules\MotionGraphics\Contracts\PlatformAdminResolverContract;
use Tests\TestCase;

class MotionAuthorizationAdapterTest extends TestCase
{
    private function makeAdapter(
        bool $flagEnabled,
        PlatformAdminResolverContract $admins = null,
        MotionPermissionGatewayContract $permissions = null,
        ?string $trustedSiteId = 'site-1',
    ): MotionAuthorizationAdapter {
        $tenantContext = Mockery::mock(TenantContextContract::class);
        $tenantContext->shouldReceive('hasFeature')->with('motion_graphics_integration')->andReturn($flagEnabled);
        $tenantContext->shouldReceive('site')->andReturn($trustedSiteId === null ? null : (object) ['id' => $trustedSiteId]);

        $admins ??= Mockery::mock(PlatformAdminResolverContract::class);
        $permissions ??= Mockery::mock(MotionPermissionGatewayContract::class);
        $auditEvents = Mockery::mock(MotionAuditEvents::class);

        return new MotionAuthorizationAdapter($tenantContext, $auditEvents, $admins, $permissions);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_authorize_denies_when_the_integration_flag_is_disabled(): void
    {
        $adapter = $this->makeAdapter(false);

        $decision = $adapter->authorize('user:1', 'motion.view', 'motion-1', 'site-1');

        $this->assertFalse($decision->allowed);
        $this->assertSame('motion_adapter_disabled', $decision->code);
    }

    public function test_authorize_denies_when_no_actor_is_authenticated(): void
    {
        Auth::shouldReceive('user')->andReturnNull();

        $decision = $this->makeAdapter(true)->authorize('user:1', 'motion.view', 'motion-1', 'site-1');

        $this->assertFalse($decision->allowed);
        $this->assertSame('unauthenticated', $decision->code);
    }

    public function test_authorize_denies_when_actor_id_does_not_match_the_authenticated_identity(): void
    {
        $actor = (object) ['id' => 1];
        Auth::shouldReceive('user')->andReturn($actor);

        $admins = Mockery::mock(PlatformAdminResolverContract::class);
        $admins->shouldReceive('actorIdentifier')->with($actor)->andReturn('user:1');

        $decision = $this->makeAdapter(true, $admins)->authorize('user:999', 'motion.view', 'motion-1', 'site-1');

        $this->assertFalse($decision->allowed);
        $this->assertSame('actor_context_mismatch', $decision->code);
    }

    public function test_authorize_denies_a_customer_whose_claimed_site_does_not_match_the_trusted_tenant(): void
    {
        $actor = (object) ['id' => 1];
        Auth::shouldReceive('user')->andReturn($actor);

        $admins = Mockery::mock(PlatformAdminResolverContract::class);
        $admins->shouldReceive('actorIdentifier')->andReturn('user:1');
        $admins->shouldReceive('isPlatformAdmin')->with($actor)->andReturn(false);

        // Trusted tenant is site-1, but the caller claims site-2.
        $decision = $this->makeAdapter(true, $admins, null, 'site-1')
            ->authorize('user:1', 'motion.use', 'motion-1', 'site-2');

        $this->assertFalse($decision->allowed);
        $this->assertSame('tenant_context_mismatch', $decision->code);
    }

    public function test_authorize_denies_when_the_ability_is_not_granted(): void
    {
        $actor = (object) ['id' => 1];
        Auth::shouldReceive('user')->andReturn($actor);

        $admins = Mockery::mock(PlatformAdminResolverContract::class);
        $admins->shouldReceive('actorIdentifier')->andReturn('user:1');
        $admins->shouldReceive('isPlatformAdmin')->andReturn(true);

        $permissions = Mockery::mock(MotionPermissionGatewayContract::class);
        $permissions->shouldReceive('allows')->with($actor, 'motion.publish')->andReturn(false);

        $decision = $this->makeAdapter(true, $admins, $permissions)
            ->authorize('user:1', 'motion.publish', 'motion-1', 'site-1');

        $this->assertFalse($decision->allowed);
        $this->assertSame('ability_denied', $decision->code);
    }

    public function test_authorize_allows_when_every_check_passes(): void
    {
        $actor = (object) ['id' => 1];
        Auth::shouldReceive('user')->andReturn($actor);

        $admins = Mockery::mock(PlatformAdminResolverContract::class);
        $admins->shouldReceive('actorIdentifier')->andReturn('user:1');
        $admins->shouldReceive('isPlatformAdmin')->andReturn(false);

        $permissions = Mockery::mock(MotionPermissionGatewayContract::class);
        $permissions->shouldReceive('allows')->with($actor, 'motion.use')->andReturn(true);

        $decision = $this->makeAdapter(true, $admins, $permissions, 'site-1')
            ->authorize('user:1', 'motion.use', 'motion-1', 'site-1');

        $this->assertTrue($decision->allowed);
    }

    public function test_validate_ownership_denies_cross_tenant_access(): void
    {
        Auth::shouldReceive('user')->andReturnNull();

        $decision = $this->makeAdapter(true, null, null, 'site-1')
            ->validateOwnership('motion-1', 'site-2', new MotionAdapterContext('user:1', 'site-1', 'corr-1'));

        $this->assertFalse($decision->owns);
        $this->assertSame('tenant_isolation_violation', $decision->code);
    }

    public function test_validate_tenant_access_allows_platform_admin_across_sites(): void
    {
        $actor = (object) ['id' => 1];
        Auth::shouldReceive('user')->andReturn($actor);

        $admins = Mockery::mock(PlatformAdminResolverContract::class);
        $admins->shouldReceive('isPlatformAdmin')->with($actor)->andReturn(true);

        $decision = $this->makeAdapter(true, $admins, null, 'site-1')
            ->validateTenantAccess('site-9', 'motion-1', new MotionAdapterContext('user:1', 'site-1', 'corr-1'));

        $this->assertTrue($decision->allowed);
        $this->assertSame('platform_admin_scope', $decision->code);
    }
}
