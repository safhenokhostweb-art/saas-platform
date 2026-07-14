<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\DriverCapabilities;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Stages\GenerateEnvironmentStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 14-G1 — before this fix, GenerateEnvironmentStage picked
 * QUEUE_CONNECTION=sync for any driver without supportsQueue (cPanel
 * included), while ConfigureQueueStage still provisioned a cron-based
 * `queue:work --stop-when-empty` worker for any driver with
 * supportsQueueWorker=true (cPanel included). A `sync` connection runs jobs
 * inline and never enqueues them, so that cron worker had nothing to ever
 * consume. These tests pin the corrected decision table:
 *
 *   supportsQueue=true                       -> redis (VPS, Docker)
 *   supportsQueue=false, supportsQueueWorker=true  -> database (cPanel)
 *   supportsQueue=false, supportsQueueWorker=false -> sync (no worker at all)
 */
class GenerateEnvironmentStageQueueDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;

        parent::setUp();
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
            'status'            => 'running',
            'customer_company'  => 'Queue Driver Co',
            'customer_email'    => 'queuedriver@example.com',
            'customer_domain'   => 'queuedriver.example.com',
            'request_payload'   => [
                'company' => 'Queue Driver Co', 'owner_name' => 'Owner',
                'email' => 'queuedriver@example.com', 'phone' => '09123456789',
                'language' => 'fa', 'timezone' => 'Asia/Tehran', 'currency' => 'IRR',
                'domain' => 'queuedriver.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily',
                'smtp' => [], 'ssl_enabled' => false,
                'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);
    }

    /**
     * Asserts against the stage's own StageResult output (which records
     * 'queue_driver' alongside the .env write — see GenerateEnvironmentStage)
     * rather than decoding the remote write command, since escapeshellarg()
     * quotes differently across platforms (single quotes on POSIX, double
     * quotes on Windows) — parsing that command string is not portable, but
     * the stage's reported queue_driver is exactly the value it wrote.
     */
    private function queueDriver(CustomerInstallation $installation, DriverCapabilities $capabilities): string
    {
        $request = CustomerInstallRequest::fromArray($installation->request_payload);
        $driver = new FakeRemoteDriver($capabilities);
        $ctx = new InstallationContext($installation, $request, $driver);

        $stage = app(GenerateEnvironmentStage::class);
        $result = $stage->execute($ctx);
        $this->assertTrue($result->success, $result->error ?? '');

        return $result->output['queue_driver'];
    }

    public function test_vps_capabilities_use_redis_queue(): void
    {
        $driver = $this->queueDriver($this->makeInstallation(), DriverCapabilities::forVps());

        $this->assertSame('redis', $driver);
    }

    public function test_cpanel_capabilities_use_database_queue_not_sync(): void
    {
        $driver = $this->queueDriver($this->makeInstallation(), DriverCapabilities::forCpanel());

        $this->assertSame('database', $driver);
    }

    public function test_driver_with_no_queue_worker_support_falls_back_to_sync(): void
    {
        $driver = $this->queueDriver($this->makeInstallation(), DriverCapabilities::forSftp());

        $this->assertSame('sync', $driver);
    }

    public function test_cpanel_queue_driver_matches_what_configure_queue_stage_provisions(): void
    {
        // Coherence check: ConfigureQueueStage installs a worker for cPanel
        // whenever supportsQueueWorker is true (regardless of supportsQueue),
        // so the .env queue connection for that same capability set must
        // never be `sync` — a `sync` connection means ConfigureQueueStage's
        // cron worker will run against an empty queue forever.
        $capabilities = DriverCapabilities::forCpanel();
        $this->assertTrue($capabilities->supportsQueueWorker);
        $this->assertFalse($capabilities->supportsQueue);

        $driver = $this->queueDriver($this->makeInstallation(), $capabilities);

        $this->assertNotSame('sync', $driver);
    }
}
