<?php

namespace Tests\Feature\Admin\Infrastructure;

use App\Jobs\CustomerInstallationPipelineJob;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 14-B1 — CustomerDeploymentController::start() must not dispatch an
 * installation against a CustomerRemoteServer whose driver type is
 * registered but not implemented. Before this phase, nothing checked this
 * here: the pipeline job would be queued, run for up to 90 minutes of
 * retries, and only then fail with "No driver registered for server type:
 * cpanel" (Installation #44). These tests fail fast at dispatch time instead.
 *
 * Both cases below intentionally fail before the DNS-existence check in
 * start() (the enforcement check runs first), so no real DNS lookup happens
 * in this test.
 */
class CustomerDeploymentControllerDriverEnforcementTest extends TestCase
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

    private function startPayload(int $serverId): array
    {
        return [
            'customer_company' => 'Test Co',
            'owner_name'       => 'Owner',
            'customer_email'   => 'owner@example.test',
            'phone'            => '09123456789',
            'language'         => 'fa',
            'timezone'         => 'Asia/Tehran',
            'currency'         => 'IRR',
            'customer_domain'  => 'customer1.example.test',
            'remote_server_id' => $serverId,
            'install_mode'     => 'MODE_FRESH',
            'license_type'     => 'basic',
        ];
    }

    public function test_start_rejects_installation_against_a_docker_server(): void
    {
        // Phase 14-C promoted cPanel to isImplemented()=true (it connects
        // over SSH transport now — see DriverType::isImplemented() and
        // CpanelDeploymentDriver's docblock); a cpanel server's
        // auth_method=api_token incompatibility is rejected later, inside
        // the driver's own validate()/connect(), not here at dispatch
        // time. docker remains a genuine architecture stub (isImplemented()
        // stays false) and, unlike 'sftp', is an actual value in the
        // customer_remote_servers.type DB enum (see its migration) — so it
        // still exercises this class's actual subject: start() rejecting a
        // truly-unimplemented driver type before dispatch.
        Bus::fake();
        $this->actingAs($this->admin());

        // Bypasses store()'s own enforcement (added in this same phase) by
        // writing directly to the DB, simulating a server that was created
        // before this phase shipped — exactly Installation #44's situation.
        $server = CustomerRemoteServer::create([
            'label' => 'Legacy Docker Box', 'type' => 'docker',
            'host' => 'box106.parsvds.com', 'port' => 22,
            'username' => 'customer1', 'auth_method' => 'api_token',
        ]);

        $response = $this->post(route('admin.infrastructure.customer-deployment.start'), $this->startPayload($server->id));

        $response->assertSessionHasErrors('remote_server_id');
        $this->assertSame(0, CustomerInstallation::count());
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
    }

    public function test_start_rejects_installation_against_a_directadmin_server(): void
    {
        Bus::fake();
        $this->actingAs($this->admin());

        $server = CustomerRemoteServer::create([
            'label' => 'Legacy DirectAdmin Box', 'type' => 'directadmin',
            'host' => 'da.example.test', 'port' => 2222,
            'username' => 'customer1', 'auth_method' => 'api_token',
        ]);

        $response = $this->post(route('admin.infrastructure.customer-deployment.start'), $this->startPayload($server->id));

        $response->assertSessionHasErrors('remote_server_id');
        $this->assertSame(0, CustomerInstallation::count());
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
    }
}
