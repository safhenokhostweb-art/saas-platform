<?php

namespace Tests\Unit\Modules\MotionGraphics\Bindings;

use App\Models\User;
use App\Modules\MotionGraphics\Bindings\PermissionGatewayAdapter;
use Mockery;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use Tests\TestCase;

class PermissionGatewayAdapterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function adapter(bool $flagEnabled = true): PermissionGatewayAdapter
    {
        $tenantContext = Mockery::mock(TenantContextContract::class);
        $tenantContext->shouldReceive('hasFeature')->with('motion_graphics_integration')->andReturn($flagEnabled);

        return new PermissionGatewayAdapter($tenantContext);
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

    public function test_platform_admin_holds_every_motion_ability(): void
    {
        $adapter = $this->adapter();

        $this->assertTrue($adapter->allows($this->admin(), 'motion.create'));
        $this->assertTrue($adapter->allows($this->admin(), 'motion.publish'));
        $this->assertTrue($adapter->allows($this->admin(), 'motion.rollback'));
    }

    public function test_customer_may_only_view_and_use_runtime_output(): void
    {
        $adapter = $this->adapter();
        $customer = $this->customer();

        $this->assertTrue($adapter->allows($customer, 'motion.view'));
        $this->assertTrue($adapter->allows($customer, 'motion.use'));
    }

    public function test_customer_cannot_reach_master_asset_management_abilities(): void
    {
        $adapter = $this->adapter();
        $customer = $this->customer();

        $this->assertFalse($adapter->allows($customer, 'motion.create'));
        $this->assertFalse($adapter->allows($customer, 'motion.publish'));
        $this->assertFalse($adapter->allows($customer, 'motion.rollback'));
        $this->assertFalse($adapter->allows($customer, 'motion.delete'));
    }

    public function test_it_fails_closed_when_the_integration_flag_is_disabled(): void
    {
        $adapter = $this->adapter(false);

        $this->assertFalse($adapter->allows($this->admin(), 'motion.create'));
    }

    public function test_non_user_actors_are_denied(): void
    {
        $this->assertFalse($this->adapter()->allows(null, 'motion.view'));
        $this->assertFalse($this->adapter()->allows('user:1', 'motion.view'));
    }
}
