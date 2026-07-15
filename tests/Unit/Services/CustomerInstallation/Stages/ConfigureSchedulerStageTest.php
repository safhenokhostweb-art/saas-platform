<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Provisioning\CpanelCronProvisioner;
use App\Services\CustomerInstallation\Provisioning\CronProvisionerFactory;
use App\Services\CustomerInstallation\Provisioning\ProvisioningResult;
use App\Services\CustomerInstallation\Provisioning\VpsCronProvisioner;
use App\Services\CustomerInstallation\Stages\ConfigureSchedulerStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 14-F3 — proves ConfigureSchedulerStage is hosting-agnostic: it is
 * exercised here against a spy provisioner that has nothing to do with cron
 * syntax or cPanel, and the stage still produces the same output/rollback
 * shape as before, because it only ever talks to CronProvisionerInterface.
 */
class ConfigureSchedulerStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function spyProvisioner(array &$calls, ProvisioningResult $installResult): VpsCronProvisioner
    {
        return new class($calls, $installResult) extends VpsCronProvisioner {
            public function __construct(private array &$calls, private ProvisioningResult $installResult) {}
            public function installScheduler(InstallationContext $ctx): ProvisioningResult
            {
                $this->calls[] = ['installScheduler'];
                return $this->installResult;
            }
            public function removeScheduler(InstallationContext $ctx, array $snapshot): void
            {
                $this->calls[] = ['removeScheduler', $snapshot];
            }
        };
    }

    private function context(FakeRemoteDriver $driver): InstallationContext
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Fake', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Scheduler Stage Co',
            'customer_email'    => 'sched@example.com',
            'customer_domain'   => 'sched.example.com',
            'request_payload'   => [
                'company' => 'Scheduler Stage Co', 'owner_name' => 'Owner', 'email' => 'sched@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'sched.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);

        return new InstallationContext($installation, $request, $driver);
    }

    public function test_execute_delegates_to_the_provisioner_and_relays_its_result(): void
    {
        $calls = [];
        $installed = ProvisioningResult::installed(['cron_path' => '/etc/cron.d/x', 'cron_entry' => '* * * * *'], ['cron_path' => '/etc/cron.d/x']);
        $spy = $this->spyProvisioner($calls, $installed);
        $cpanelDummy = $this->createStub(CpanelCronProvisioner::class);

        $stage = new ConfigureSchedulerStage(new CronProvisionerFactory($spy, $cpanelDummy));
        $ctx = $this->context(new FakeRemoteDriver());

        $result = $stage->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertSame($installed->output, $result->output);
        $this->assertSame($installed->snapshot, $result->rollbackSnapshot);
        $this->assertSame(['installScheduler'], $calls[0]);
    }

    public function test_execute_relays_a_skipped_result_as_a_successful_stage_result(): void
    {
        $calls = [];
        $skipped = ProvisioningResult::skipped('manual cron setup required');
        $spy = $this->spyProvisioner($calls, $skipped);
        $cpanelDummy = $this->createStub(CpanelCronProvisioner::class);

        $stage = new ConfigureSchedulerStage(new CronProvisionerFactory($spy, $cpanelDummy));
        $ctx = $this->context(new FakeRemoteDriver());

        $result = $stage->execute($ctx);

        $this->assertTrue($result->success, 'A skip must not fail the installation');
        $this->assertSame('skipped', $result->output['status']);
        $this->assertSame([], $result->rollbackSnapshot);
    }

    public function test_rollback_delegates_to_the_provisioner(): void
    {
        $calls = [];
        $spy = $this->spyProvisioner($calls, ProvisioningResult::skipped('n/a'));
        $cpanelDummy = $this->createStub(CpanelCronProvisioner::class);

        $stage = new ConfigureSchedulerStage(new CronProvisionerFactory($spy, $cpanelDummy));
        $ctx = $this->context(new FakeRemoteDriver());

        $stage->rollback($ctx, ['cron_path' => '/etc/cron.d/x']);

        $this->assertSame(['removeScheduler', ['cron_path' => '/etc/cron.d/x']], $calls[0]);
    }

    public function test_required_capabilities_declares_cron(): void
    {
        $stage = new ConfigureSchedulerStage(new CronProvisionerFactory(
            new VpsCronProvisioner(),
            $this->createStub(CpanelCronProvisioner::class),
        ));

        $this->assertSame(['supportsCron'], $stage->requiredCapabilities());
        $this->assertFalse($stage->isCapabilityRequiredToProceed());
    }
}
