<?php

namespace Tests\Feature\CustomerInstallation;

use App\Jobs\CustomerInstallationRollbackJob;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CustomerInstallationOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase 13-O3 — CustomerInstallationOrchestrator::run() used to call its own
 * private dispatchRollback() directly; that logic was extracted into the
 * shared RollbackDispatcher service. This test drives a full orchestrator
 * run through a real stage failure (not a stage unit test) to confirm the
 * end-to-end "stage fails -> rollback queued" behavior is unchanged after
 * the extraction.
 */
class OrchestratorRollbackDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function makeServer(): CustomerRemoteServer
    {
        return CustomerRemoteServer::create([
            'label'       => 'Test Server',
            'type'        => 'fake',
            'host'        => 'localhost',
            'port'        => 22,
            'username'    => 'deploy',
            'auth_method' => 'key',
        ]);
    }

    private function makeInstallation(CustomerRemoteServer $server): CustomerInstallation
    {
        return CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'pending',
            'customer_company'  => 'Orchestrator Rollback Co',
            'customer_email'    => 'orch@example.com',
            'customer_domain'   => 'orch.example.com',
            'request_payload'   => [
                'company' => 'Orchestrator Rollback Co', 'owner_name' => 'Owner',
                'email' => 'orch@example.com', 'phone' => '09123456789',
                'language' => 'fa', 'timezone' => 'Asia/Tehran', 'currency' => 'IRR',
                'domain' => 'orch.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily',
                'smtp' => [], 'ssl_enabled' => false,
                'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);
    }

    public function test_a_failed_stage_still_dispatches_rollback_after_extraction(): void
    {
        Queue::fake();

        // FakeRemoteDriver::execute() returns empty stdout/exit 0 for any
        // command with no canned response, so VALIDATE_PHP (the first stage
        // that inspects command output rather than just the exit code)
        // fails deterministically without needing to touch the driver the
        // orchestrator builds internally.
        $server = $this->makeServer();
        $installation = $this->makeInstallation($server);

        app(CustomerInstallationOrchestrator::class)->run($installation);

        $installation->refresh();
        $this->assertSame('failed', $installation->status);
        $this->assertNotEmpty($installation->failure_reason);

        Queue::assertPushedOn(
            'customer-install',
            CustomerInstallationRollbackJob::class,
            fn ($job) => $job->installationId === $installation->id
        );
    }
}
