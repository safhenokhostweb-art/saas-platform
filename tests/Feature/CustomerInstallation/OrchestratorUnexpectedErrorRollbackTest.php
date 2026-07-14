<?php

namespace Tests\Feature\CustomerInstallation;

use App\Jobs\CustomerInstallationRollbackJob;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Contracts\InstallationStateInterface;
use App\Services\CustomerInstallation\CustomerInstallationOrchestrator;
use App\Services\CustomerInstallation\InstallationStateManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase 14-G1 — CustomerInstallationOrchestrator::run()'s outer catch(\Throwable)
 * block (the one guarding against errors that escape a stage's own execute(),
 * e.g. a throw from buildPreSnapshot() or from recordStageStart() itself) used
 * to call $this->dispatchRollback($installation) — a method that does not
 * exist on this class. That call itself fatally errored with
 * "Call to undefined method ...::dispatchRollback()", so an unexpected
 * pipeline exception never reached the correct rollback path; it just
 * produced a second, misleading fatal error.
 *
 * This test forces an exception from inside the try block but OUTSIDE any
 * stage's own AbstractStage::run() wrapper (by making the state manager
 * throw from recordStageStart() for the first stage), which is exactly the
 * path the buggy catch-all handled. Before the fix, this test would fail
 * with an uncaught Error instead of the assertions below ever running.
 */
class OrchestratorUnexpectedErrorRollbackTest extends TestCase
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
            'label'       => 'Test Server',
            'type'        => 'fake',
            'host'        => 'localhost',
            'port'        => 22,
            'username'    => 'deploy',
            'auth_method' => 'key',
        ]);

        return CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'pending',
            'customer_company'  => 'Unexpected Error Co',
            'customer_email'    => 'unexpected@example.com',
            'customer_domain'   => 'unexpected.example.com',
            'request_payload'   => [
                'company' => 'Unexpected Error Co', 'owner_name' => 'Owner',
                'email' => 'unexpected@example.com', 'phone' => '09123456789',
                'language' => 'fa', 'timezone' => 'Asia/Tehran', 'currency' => 'IRR',
                'domain' => 'unexpected.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily',
                'smtp' => [], 'ssl_enabled' => false,
                'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);
    }

    public function test_an_exception_outside_stage_execution_still_dispatches_rollback(): void
    {
        Queue::fake();

        $real = new InstallationStateManager();
        $failingState = new class($real) implements InstallationStateInterface {
            public function __construct(private readonly InstallationStateManager $real) {}

            public function markRunning($installation): void { $this->real->markRunning($installation); }

            public function recordStageStart($installation, string $stage): void
            {
                // Throw from inside the orchestrator's try block, but outside
                // any stage's own execute()/run() wrapper — the exact class
                // of error the outer catch(\Throwable) exists to handle.
                throw new \RuntimeException('Simulated infra failure outside stage execution');
            }

            public function recordStageComplete($installation, string $stage, array $output, int $durationMs): void
            { $this->real->recordStageComplete($installation, $stage, $output, $durationMs); }

            public function recordStageFailure($installation, string $stage, string $error, int $durationMs): void
            { $this->real->recordStageFailure($installation, $stage, $error, $durationMs); }

            public function recordRollbackCheckpoint($installation, string $stage, array $snapshot): void
            { $this->real->recordRollbackCheckpoint($installation, $stage, $snapshot); }

            public function recordWarning($installation, string $stage, string $message): void
            { $this->real->recordWarning($installation, $stage, $message); }

            public function markComplete($installation): void { $this->real->markComplete($installation); }

            public function markFailed($installation, string $reason): void
            { $this->real->markFailed($installation, $reason); }

            public function markRollingBack($installation): void { $this->real->markRollingBack($installation); }

            public function markRolledBack($installation): void { $this->real->markRolledBack($installation); }

            public function markRollbackFailed($installation, string $reason): void
            { $this->real->markRollbackFailed($installation, $reason); }
        };

        $this->app->instance(InstallationStateInterface::class, $failingState);

        $installation = $this->makeInstallation();

        // Must not throw — the whole point of the fix is that the outer
        // catch(\Throwable) handles this gracefully instead of itself
        // fataling on an undefined method call.
        app(CustomerInstallationOrchestrator::class)->run($installation);

        $installation->refresh();
        $this->assertSame('failed', $installation->status);
        $this->assertStringContainsString('Unexpected pipeline error', $installation->failure_reason);

        Queue::assertPushedOn(
            'customer-install',
            CustomerInstallationRollbackJob::class,
            fn ($job) => $job->installationId === $installation->id
        );
    }
}
