<?php

namespace Tests\Unit\CustomerInstallation\Provisioning;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Provisioning\VpsCronProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 14-F3 — VpsCronProvisioner was extracted verbatim from the
 * pre-14-F3 ConfigureSchedulerStage. Pins the exact cron file path/content
 * and the skip-on-write-failure behavior so the extraction is a no-op.
 */
class VpsCronProvisionerTest extends TestCase
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
            'customer_company'  => 'Cron Vps Co',
            'customer_email'    => 'cronvps@example.com',
            'customer_domain'   => 'cronvps.example.com',
            'request_payload'   => [
                'company' => 'Cron Vps Co', 'owner_name' => 'Owner', 'email' => 'cronvps@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'cronvps.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);

        return new InstallationContext($installation, $request, $driver);
    }

    public function test_installs_the_expected_cron_d_file(): void
    {
        $driver = new FakeRemoteDriver();
        // Real remote command ends with "&& echo 'ok'" — FakeRemoteDriver's
        // default response has empty stdout, which the provisioner reads as
        // "write failed" (correctly, for the failure test below). Register
        // the success echo explicitly for this happy-path test.
        $driver->addResponse('chmod 644', 0, 'ok');
        $ctx = $this->context($driver);

        $result = (new VpsCronProvisioner())->installScheduler($ctx);

        $this->assertTrue($result->installed);
        $expectedSlug = $ctx->customerSlug();
        $this->assertSame("/etc/cron.d/{$expectedSlug}", $result->output['cron_path']);
        $this->assertStringContainsString('php artisan schedule:run', $result->output['cron_entry']);
        $this->assertSame(['cron_path' => "/etc/cron.d/{$expectedSlug}"], $result->snapshot);

        $log = $driver->getCommandLog();
        $this->assertStringContainsString('base64 -d >', $log[0]);
        $this->assertStringContainsString('chmod 644', $log[0]);
    }

    public function test_skips_gracefully_when_the_write_fails(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('base64 -d', 1, 'permission denied');
        $ctx = $this->context($driver);

        $result = (new VpsCronProvisioner())->installScheduler($ctx);

        $this->assertFalse($result->installed);
        $this->assertSame('skipped', $result->output['status']);
        $this->assertSame([], $result->snapshot);
    }

    public function test_remove_scheduler_deletes_the_cron_file(): void
    {
        $driver = new FakeRemoteDriver();
        $ctx = $this->context($driver);

        (new VpsCronProvisioner())->removeScheduler($ctx, ['cron_path' => '/etc/cron.d/cust_x_1']);

        $log = $driver->getCommandLog();
        $this->assertStringContainsString('rm -f', $log[0]);
        $this->assertStringContainsString('/etc/cron.d/cust_x_1', $log[0]);
    }

    public function test_remove_scheduler_is_a_no_op_without_a_snapshot(): void
    {
        $driver = new FakeRemoteDriver();
        $ctx = $this->context($driver);

        (new VpsCronProvisioner())->removeScheduler($ctx, []);

        $this->assertCount(0, $driver->getCommandLog());
    }
}
