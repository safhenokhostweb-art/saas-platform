<?php

namespace Tests\Unit\Modules\MotionGraphics\Bindings;

use App\Models\User;
use App\Modules\MotionGraphics\Bindings\PlatformAdminResolverAdapter;
use Mockery;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use Tests\TestCase;

class PlatformAdminResolverAdapterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function adapter(bool $flagEnabled): PlatformAdminResolverAdapter
    {
        $tenantContext = Mockery::mock(TenantContextContract::class);
        $tenantContext->shouldReceive('hasFeature')->with('motion_graphics_integration')->andReturn($flagEnabled);

        return new PlatformAdminResolverAdapter($tenantContext);
    }

    private function admin(): User
    {
        $user = new User();
        $user->id = 1;
        $user->is_admin = true;
        $user->site_id = null;

        return $user;
    }

    private function customer(): User
    {
        $user = new User();
        $user->id = 2;
        $user->is_admin = false;
        $user->site_id = 7;

        return $user;
    }

    public function test_a_global_admin_is_recognized_as_platform_admin_when_the_flag_is_enabled(): void
    {
        $this->assertTrue($this->adapter(true)->isPlatformAdmin($this->admin()));
    }

    public function test_a_site_scoped_user_is_never_a_platform_admin_even_if_is_admin_is_set(): void
    {
        $user = $this->admin();
        $user->site_id = 5;

        $this->assertFalse($this->adapter(true)->isPlatformAdmin($user));
    }

    public function test_admin_status_fails_closed_when_the_integration_flag_is_disabled(): void
    {
        $this->assertFalse($this->adapter(false)->isPlatformAdmin($this->admin()));
    }

    public function test_a_customer_is_never_a_platform_admin(): void
    {
        $this->assertFalse($this->adapter(true)->isPlatformAdmin($this->customer()));
    }

    public function test_actor_identifier_is_stable_for_a_given_user(): void
    {
        $this->assertSame('user:1', $this->adapter(true)->actorIdentifier($this->admin()));
    }
}
