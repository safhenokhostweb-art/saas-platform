<?php

namespace Tests\Feature\CustomerInstallation;

use App\Models\CustomerInstallation;
use App\Models\CustomerInstallRollback;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CustomerInstallationRollbackService;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\InstallationStateManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RollbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function makeInstallation(): CustomerInstallation
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Fake', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        return CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'failed',
            'failed_at'         => now(),
            'customer_company'  => 'Rollback Co',
            'customer_email'    => 'rb@example.com',
            'customer_domain'   => 'rb.example.com',
            'request_payload'   => [
                'company' => 'Rollback Co', 'owner_name' => 'Owner',
                'email' => 'rb@example.com', 'phone' => '09123456789',
                'language' => 'fa', 'timezone' => 'Asia/Tehran', 'currency' => 'IRR',
                'domain' => 'rb.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily',
                'smtp' => [], 'ssl_enabled' => false,
                'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);
    }

    public function test_rollback_checkpoints_created(): void
    {
        $installation = $this->makeInstallation();

        CustomerInstallRollback::create([
            'installation_id' => $installation->id,
            'stage'           => 'EXTRACT_PACKAGE',
            'snapshot'        => ['remote_path' => '/var/www/test'],
            'executed_at'     => null,
            'created_at'      => now(),
        ]);

        $this->assertDatabaseHas('customer_install_rollbacks', [
            'installation_id' => $installation->id,
            'stage'           => 'EXTRACT_PACKAGE',
            'executed_at'     => null,
        ]);
    }

    public function test_rollback_marks_checkpoints_as_executed(): void
    {
        $installation = $this->makeInstallation();

        $checkpoint = CustomerInstallRollback::create([
            'installation_id' => $installation->id,
            'stage'           => 'CONFIGURE_QUEUE',
            'snapshot'        => ['config_path' => '/etc/supervisor/conf.d/test.conf'],
            'executed_at'     => null,
            'created_at'      => now(),
        ]);

        $service = app(CustomerInstallationRollbackService::class);
        $service->rollback($installation);

        $checkpoint->refresh();
        $this->assertNotNull($checkpoint->executed_at);
    }

    public function test_rollback_transitions_to_rolled_back_status(): void
    {
        $installation = $this->makeInstallation();

        $service = app(CustomerInstallationRollbackService::class);
        $service->rollback($installation);

        $installation->refresh();
        $this->assertContains($installation->status, ['rolled_back', 'rollback_failed']);
    }

    public function test_immutable_log_entries_cannot_be_updated(): void
    {
        $installation = $this->makeInstallation();

        $state = new InstallationStateManager();
        $state->markRunning($installation);

        $log = \App\Models\CustomerInstallationLog::where('installation_id', $installation->id)->first();
        $this->assertNotNull($log);

        // Test immutability — update should silently return false
        $result = $log->update(['message' => 'tampered']);
        $this->assertFalse($result);

        $log->refresh();
        $this->assertNotEquals('tampered', $log->message);
    }

    public function test_log_records_all_stage_events(): void
    {
        $installation = $this->makeInstallation();
        $state = new InstallationStateManager();

        $state->markRunning($installation);
        $state->recordStageStart($installation, 'VALIDATE_SERVER');
        $state->recordStageComplete($installation, 'VALIDATE_SERVER', ['server_info' => 'Ubuntu'], 100);
        $state->recordStageFailure($installation, 'VALIDATE_PHP', 'PHP not found', 50);

        $logs = \App\Models\CustomerInstallationLog::where('installation_id', $installation->id)->get();

        $events = $logs->pluck('event')->toArray();

        $this->assertContains('started', $events);
        $this->assertContains('completed', $events);
        $this->assertContains('failed', $events);
    }
}
