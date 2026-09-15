<?php

namespace Tests\Feature\Admin\Infrastructure;

use App\Models\CustomerRemoteServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 14-B1 — closes the gap that let Installation #44 happen: the admin
 * UI already labeled cpanel/directadmin/sftp/docker as "implemented => false"
 * in driverMetaMap(), but nothing stopped the store() form from actually
 * creating a CustomerRemoteServer of one of those types, which later
 * dispatched an installation guaranteed to fail deep in the pipeline.
 */
class CustomerRemoteServerAdminControllerDriverEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function admin(): User
    {
        return User::create([
            'mobile' => '09131234567',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_active' => true,
        ]);
    }

    public function test_sftp_server_is_rejected(): void
    {
        // Phase 14-C promoted cPanel to DriverType::isImplemented()=true
        // (SSH transport — see CpanelDeploymentDriver's docblock), so
        // store() no longer rejects it here; a cpanel server's
        // auth_method=api_token incompatibility is now rejected later,
        // inside the driver's own validate()/connect(). sftp remains a
        // genuine architecture stub, so it still exercises this class's
        // actual subject: store() rejecting a truly-unimplemented type.
        $this->actingAs($this->admin());

        $response = $this->post(route('admin.infrastructure.customer-deployment.servers.store'), [
            'label'       => 'Test SFTP Box',
            'type'        => 'sftp',
            'host'        => 'box106.parsvds.com',
            'port'        => 22,
            'username'    => 'customer1',
            'auth_method' => 'password',
            'password'    => 'a-valid-looking-password',
        ]);

        $response->assertSessionHasErrors('type');
        $this->assertDatabaseMissing('customer_remote_servers', ['type' => 'sftp']);
    }

    public function test_directadmin_server_is_rejected(): void
    {
        $this->actingAs($this->admin());

        $response = $this->post(route('admin.infrastructure.customer-deployment.servers.store'), [
            'label'       => 'Test DirectAdmin Box',
            'type'        => 'directadmin',
            'host'        => 'da.example.test',
            'port'        => 2222,
            'username'    => 'customer1',
            'auth_method' => 'api_token',
            'api_token'   => 'a-valid-looking-token',
        ]);

        $response->assertSessionHasErrors('type');
        $this->assertDatabaseMissing('customer_remote_servers', ['type' => 'directadmin']);
    }

    public function test_ssh_server_is_still_accepted(): void
    {
        $this->actingAs($this->admin());

        $response = $this->post(route('admin.infrastructure.customer-deployment.servers.store'), [
            'label'       => 'Test VPS',
            'type'        => 'ssh',
            'host'        => 'vps.example.test',
            'port'        => 22,
            'username'    => 'deploy',
            'auth_method' => 'password',
            'password'    => 'a-valid-looking-password',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customer_remote_servers', [
            'label' => 'Test VPS',
            'type'  => 'ssh',
        ]);
    }
}
