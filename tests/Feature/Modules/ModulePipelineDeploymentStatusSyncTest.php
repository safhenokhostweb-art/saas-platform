<?php

namespace Tests\Feature\Modules;

use App\Jobs\CustomerInstallationPipelineJob;
use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Module;
use App\Models\Site;
use App\Services\CustomerInstallation\Contracts\InstallationStateInterface;
use App\Services\CustomerInstallation\CustomerInstallationOrchestrator;
use App\Services\CustomerInstallation\InstallationStateManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase 17-C — Part 5/8: verifies CustomerInstallationPipelineJob's
 * post-run sync closes the "pending status never resolves" gap for
 * MODE_MODULE_INSTALL/MODE_MODULE_UPDATE, end-to-end through the job —
 * not just the sync method in isolation.
 *
 * Forces a deterministic pipeline failure the same way
 * OrchestratorUnexpectedErrorRollbackTest does (a failing
 * InstallationStateInterface double thrown from recordStageStart()) rather
 * than relying on FakeRemoteDriver's default responses happening to fail a
 * particular stage — this test's outcome must not depend on incidental
 * stage-ordering/behavior elsewhere in the pipeline.
 */
class ModulePipelineDeploymentStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function makeSiteAndModule(): array
    {
        $customer = Customer::create(['name' => 'مشتری تست', 'mobile' => '0912' . random_int(1000000, 9999999)]);
        $site = Site::create([
            'customer_id' => $customer->id, 'title' => 'سایت تست',
            'domain' => 'test-' . uniqid('', true) . '.example.com', 'is_active' => true,
        ]);
        $module = Module::updateOrCreate(
            ['slug' => 'forms'],
            ['title' => 'ماژول فرم', 'is_active' => true, 'version' => '1.0.0', 'type' => Module::TYPE_CORE]
        );
        $server = CustomerRemoteServer::create([
            'label' => 'Test Server', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        return compact('site', 'module', 'server');
    }

    private function failingStateManager(): InstallationStateInterface
    {
        $real = new InstallationStateManager();

        return new class($real) implements InstallationStateInterface {
            public function __construct(private readonly InstallationStateManager $real) {}
            public function markRunning($installation): void { $this->real->markRunning($installation); }
            public function recordStageStart($installation, string $stage): void
            {
                throw new \RuntimeException('Simulated infra failure — deterministic pipeline failure for this test.');
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
    }

    public function test_a_failed_module_install_pipeline_marks_site_module_deployment_failed(): void
    {
        Queue::fake();
        $this->app->instance(InstallationStateInterface::class, $this->failingStateManager());

        ['site' => $site, 'module' => $module, 'server' => $server] = $this->makeSiteAndModule();

        DB::table('site_modules')->insert([
            'site_id' => $site->id, 'module_id' => $module->id,
            'is_active' => true, 'deployment_status' => 'pending_install',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id' => $server->id,
            'installer_user_id' => 1,
            'install_mode' => 'MODE_MODULE_INSTALL',
            'status' => 'pending',
            'site_id' => $site->id,
            'customer_company' => 'Test Co',
            'customer_email' => 'test@example.com',
            'customer_domain' => $site->domain,
            'request_payload' => [
                'company' => 'Test Co', 'owner_name' => 'Owner', 'email' => 'test@example.com',
                'phone' => '09123456789', 'domain' => $site->domain,
                'remote_server_id' => $server->id, 'install_mode' => 'MODE_MODULE_INSTALL',
            ],
            'metadata' => ['module_slug' => $module->slug, 'module_release_id' => 1, 'target_version' => '1.0.0'],
        ]);

        (new CustomerInstallationPipelineJob($installation->id))->handle(app(CustomerInstallationOrchestrator::class));

        $installation->refresh();
        $this->assertSame('failed', $installation->status);

        $row = DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $module->id)->first();
        $this->assertSame('failed', $row->deployment_status);
    }

    /**
     * A row already 'installed' must never be downgraded to 'failed' by a
     * later, unrelated pipeline run's failure sync (e.g. a stale/duplicate
     * callback) — see ModuleUpdateEligibilityService::markDeploymentFailed().
     */
    public function test_sync_never_downgrades_an_already_installed_row(): void
    {
        Queue::fake();
        $this->app->instance(InstallationStateInterface::class, $this->failingStateManager());

        ['site' => $site, 'module' => $module, 'server' => $server] = $this->makeSiteAndModule();

        DB::table('site_modules')->insert([
            'site_id' => $site->id, 'module_id' => $module->id,
            'is_active' => true, 'deployment_status' => 'installed', 'installed_version' => '1.0.0',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id' => $server->id,
            'installer_user_id' => 1,
            'install_mode' => 'MODE_MODULE_UPDATE',
            'status' => 'pending',
            'site_id' => $site->id,
            'customer_company' => 'Test Co',
            'customer_email' => 'test@example.com',
            'customer_domain' => $site->domain,
            'request_payload' => [
                'company' => 'Test Co', 'owner_name' => 'Owner', 'email' => 'test@example.com',
                'phone' => '09123456789', 'domain' => $site->domain,
                'remote_server_id' => $server->id, 'install_mode' => 'MODE_MODULE_UPDATE',
            ],
            'metadata' => ['module_slug' => $module->slug, 'module_release_id' => 1, 'target_version' => '1.1.0'],
        ]);

        (new CustomerInstallationPipelineJob($installation->id))->handle(app(CustomerInstallationOrchestrator::class));

        $row = DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $module->id)->first();
        $this->assertSame('installed', $row->deployment_status);
    }
}
