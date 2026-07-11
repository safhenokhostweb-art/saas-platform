<?php

namespace Tests\Unit\Jobs;

use App\Jobs\CustomerInstallationPipelineJob;
use App\Jobs\CustomerInstallationRollbackJob;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase 13-O3 — CustomerInstallationPipelineJob::failed() is the failure
 * path the orchestrator itself never sees (queue timeout, worker crash,
 * exhausted retries). Before this phase it marked the installation failed
 * and alerted, but never queued a rollback — a tenant could be left
 * mid-upgrade indefinitely. These tests cover the gap being closed.
 */
class CustomerInstallationPipelineJobFailedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function makeInstallation(string $status = 'running'): CustomerInstallation
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Fake', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        return CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => $status,
            'started_at'        => now(),
            'customer_company'  => 'Crash Co',
            'customer_email'    => 'crash@example.com',
            'customer_domain'   => 'crash.example.com',
            'request_payload'   => [
                'company' => 'Crash Co', 'owner_name' => 'Owner',
                'email' => 'crash@example.com', 'phone' => '09123456789',
                'language' => 'fa', 'timezone' => 'Asia/Tehran', 'currency' => 'IRR',
                'domain' => 'crash.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily',
                'smtp' => [], 'ssl_enabled' => false,
                'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);
    }

    /**
     * Scenario 1 — a job crash/timeout (Laravel invoking failed() instead of
     * handle() completing) must still result in rollback being queued.
     */
    public function test_failed_dispatches_rollback_for_a_crashed_job(): void
    {
        Queue::fake();
        $installation = $this->makeInstallation(status: 'running');

        $job = new CustomerInstallationPipelineJob($installation->id);
        $job->failed(new \RuntimeException('worker crashed mid-upgrade'));

        $this->assertSame('failed', $installation->fresh()->status);

        Queue::assertPushedOn(
            'customer-install',
            CustomerInstallationRollbackJob::class,
            fn ($rollbackJob) => $rollbackJob->installationId === $installation->id
        );
    }

    public function test_failed_does_nothing_when_installation_no_longer_exists(): void
    {
        Queue::fake();

        $job = new CustomerInstallationPipelineJob(999999);
        $job->failed(new \RuntimeException('installation already deleted'));

        Queue::assertNotPushed(CustomerInstallationRollbackJob::class);
    }

    /**
     * Scenario 3 — if the rollback dispatch itself can't be queued (e.g. the
     * queue connection is unavailable), that must not be silently swallowed
     * by a bare log line; it needs its own alert since it means nothing will
     * ever recover this tenant automatically.
     */
    public function test_failed_alerts_when_rollback_dispatch_itself_fails(): void
    {
        config(['services.slack_alert.webhook' => 'https://hooks.example.test/webhook']);
        Http::fake();

        Queue::shouldReceive('connection')
            ->andThrow(new \RuntimeException('queue connection unavailable'));

        $installation = $this->makeInstallation(status: 'running');

        $job = new CustomerInstallationPipelineJob($installation->id);
        $job->failed(new \RuntimeException('worker crashed mid-upgrade'));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'hooks.example.test')
                && str_contains(json_encode($request->data()), 'ROLLBACK DISPATCH FAILED');
        });
    }
}
