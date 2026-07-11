<?php

namespace Tests\Unit\Services\CustomerInstallation;

use App\Jobs\CustomerInstallationRollbackJob;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\RollbackDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase 13-O3 — RollbackDispatcher is the single place that queues
 * CustomerInstallationRollbackJob, shared by CustomerInstallationOrchestrator
 * and CustomerInstallationPipelineJob::failed().
 */
class RollbackDispatcherTest extends TestCase
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
            'customer_company'  => 'Dispatcher Co',
            'customer_email'    => 'dispatcher@example.com',
            'customer_domain'   => 'dispatcher.example.com',
            'request_payload'   => [
                'company' => 'Dispatcher Co', 'owner_name' => 'Owner',
                'email' => 'dispatcher@example.com', 'phone' => '09123456789',
                'language' => 'fa', 'timezone' => 'Asia/Tehran', 'currency' => 'IRR',
                'domain' => 'dispatcher.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily',
                'smtp' => [], 'ssl_enabled' => false,
                'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);
    }

    public function test_dispatch_queues_rollback_job_and_returns_true(): void
    {
        Queue::fake();
        $installation = $this->makeInstallation();

        $result = (new RollbackDispatcher())->dispatch($installation);

        $this->assertTrue($result);
        Queue::assertPushedOn(
            'customer-install',
            CustomerInstallationRollbackJob::class,
            fn ($job) => $job->installationId === $installation->id
        );
    }

    public function test_dispatch_returns_false_when_queue_push_fails(): void
    {
        Queue::shouldReceive('connection')
            ->andThrow(new \RuntimeException('queue connection unavailable'));

        $installation = $this->makeInstallation();

        $result = (new RollbackDispatcher())->dispatch($installation);

        $this->assertFalse($result);
    }
}
