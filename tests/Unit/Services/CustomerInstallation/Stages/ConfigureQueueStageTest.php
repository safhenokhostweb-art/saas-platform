<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Provisioning\CpanelQueueProvisioner;
use App\Services\CustomerInstallation\Provisioning\ProvisioningResult;
use App\Services\CustomerInstallation\Provisioning\QueueProvisionerFactory;
use App\Services\CustomerInstallation\Provisioning\VpsQueueProvisioner;
use App\Services\CustomerInstallation\Stages\ConfigureQueueStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 14-F3 — proves ConfigureQueueStage is hosting-agnostic, mirroring
 * ConfigureSchedulerStageTest.
 */
class ConfigureQueueStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function spyProvisioner(array &$calls, ProvisioningResult $installResult): VpsQueueProvisioner
    {
        return new class($calls, $installResult) extends VpsQueueProvisioner {
            public function __construct(private array &$calls, private ProvisioningResult $installResult) {}
            public function installWorker(InstallationContext $ctx): ProvisioningResult
            {
                $this->calls[] = ['installWorker'];
                return $this->installResult;
            }
            public function removeWorker(InstallationContext $ctx, array $snapshot): void
            {
                $this->calls[] = ['removeWorker', $snapshot];
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
            'customer_company'  => 'Queue Stage Co',
            'customer_email'    => 'queue@example.com',
            'customer_domain'   => 'queue.example.com',
            'request_payload'   => [
                'company' => 'Queue Stage Co', 'owner_name' => 'Owner', 'email' => 'queue@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'queue.example.com', 'license_type' => 'basic',
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
        $installed = ProvisioningResult::installed(
            ['config_path' => '/etc/supervisor/conf.d/x.conf', 'worker_status' => 'RUNNING'],
            ['config_path' => '/etc/supervisor/conf.d/x.conf']
        );
        $spy = $this->spyProvisioner($calls, $installed);
        $cpanelDummy = $this->createStub(CpanelQueueProvisioner::class);

        $stage = new ConfigureQueueStage(new QueueProvisionerFactory($spy, $cpanelDummy));
        $ctx = $this->context(new FakeRemoteDriver());

        $result = $stage->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertSame($installed->output, $result->output);
        $this->assertSame($installed->snapshot, $result->rollbackSnapshot);
        $this->assertSame(['installWorker'], $calls[0]);
    }

    public function test_execute_relays_a_skipped_result_as_a_successful_stage_result(): void
    {
        $calls = [];
        $skipped = ProvisioningResult::skipped('manual Supervisor setup required');
        $spy = $this->spyProvisioner($calls, $skipped);
        $cpanelDummy = $this->createStub(CpanelQueueProvisioner::class);

        $stage = new ConfigureQueueStage(new QueueProvisionerFactory($spy, $cpanelDummy));
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
        $cpanelDummy = $this->createStub(CpanelQueueProvisioner::class);

        $stage = new ConfigureQueueStage(new QueueProvisionerFactory($spy, $cpanelDummy));
        $ctx = $this->context(new FakeRemoteDriver());

        $stage->rollback($ctx, ['config_path' => '/etc/supervisor/conf.d/x.conf']);

        $this->assertSame(['removeWorker', ['config_path' => '/etc/supervisor/conf.d/x.conf']], $calls[0]);
    }

    public function test_required_capabilities_declares_queue_worker_not_supervisor(): void
    {
        $stage = new ConfigureQueueStage(new QueueProvisionerFactory(
            new VpsQueueProvisioner(),
            $this->createStub(CpanelQueueProvisioner::class),
        ));

        $this->assertSame(['supportsQueueWorker'], $stage->requiredCapabilities());
        $this->assertFalse($stage->isCapabilityRequiredToProceed());
    }
}
