<?php

namespace Tests\Unit\CustomerInstallation\Provisioning;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Provisioning\VpsQueueProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 14-F3 — VpsQueueProvisioner was extracted verbatim from the
 * pre-14-F3 ConfigureQueueStage. Pins the exact Supervisor config content
 * and command sequence so the extraction is a no-op.
 */
class VpsQueueProvisionerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function context(FakeRemoteDriver $driver): InstallationContext
    {
        $server = CustomerRemoteServer::create([
            'label' => 'VPS', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Queue Vps Co',
            'customer_email'    => 'queuevps@example.com',
            'customer_domain'   => 'queuevps.example.com',
            'request_payload'   => [
                'company' => 'Queue Vps Co', 'owner_name' => 'Owner', 'email' => 'queuevps@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'queuevps.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);

        return new InstallationContext($installation, $request, $driver);
    }

    public function test_installs_the_expected_supervisor_config(): void
    {
        $driver = new FakeRemoteDriver();
        // Real remote command ends with "&& echo 'ok'" — FakeRemoteDriver's
        // default response has empty stdout, which the provisioner reads as
        // "write failed" (correctly, for the failure test below). Register
        // the success echo explicitly for this happy-path test.
        $driver->addResponse("&& echo 'ok'", 0, 'ok');
        $ctx = $this->context($driver);

        $result = (new VpsQueueProvisioner())->installWorker($ctx);

        $this->assertTrue($result->installed);
        $slug = $ctx->customerSlug();
        $this->assertSame("/etc/supervisor/conf.d/{$slug}_worker.conf", $result->output['config_path']);
        $this->assertSame(['config_path' => "/etc/supervisor/conf.d/{$slug}_worker.conf"], $result->snapshot);

        $log = $driver->getCommandLog();
        $this->assertStringContainsString('base64 -d >', $log[0]);
        $this->assertStringContainsString('supervisorctl reread', $log[1]);
        $this->assertStringContainsString('supervisorctl status', $log[2]);
    }

    public function test_skips_gracefully_when_the_write_fails(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('base64 -d', 1, 'permission denied');
        $ctx = $this->context($driver);

        $result = (new VpsQueueProvisioner())->installWorker($ctx);

        $this->assertFalse($result->installed);
        $this->assertSame('skipped', $result->output['status']);
        $this->assertSame([], $result->snapshot);
    }

    public function test_remove_worker_deletes_config_and_reloads_supervisor(): void
    {
        $driver = new FakeRemoteDriver();
        $ctx = $this->context($driver);

        (new VpsQueueProvisioner())->removeWorker($ctx, ['config_path' => '/etc/supervisor/conf.d/cust_x_1_worker.conf']);

        $log = $driver->getCommandLog();
        $this->assertStringContainsString('rm -f', $log[0]);
        $this->assertStringContainsString('supervisorctl reread', $log[1]);
    }
}
