<?php

namespace Tests\Unit\CustomerDeletion;

use App\Models\CustomerDeletion;
use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationLog;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Stages\RemoveQueueStage;
use App\Services\CustomerDeletion\Stages\RemoveSchedulerStage;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Enums\DriverType;
use App\Services\CustomerInstallation\Provisioning\CpanelCronLine;
use App\Services\CustomerInstallation\Provisioning\CpanelCronProvisioner;
use App\Services\CustomerInstallation\Provisioning\CpanelQueueProvisioner;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use App\Services\CustomerInstallation\Provisioning\CronProvisionerFactory;
use App\Services\CustomerInstallation\Provisioning\QueueProvisionerFactory;
use App\Services\CustomerInstallation\Provisioning\VpsCronProvisioner;
use App\Services\CustomerInstallation\Provisioning\VpsQueueProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 14-G3 — RemoveSchedulerStage / RemoveQueueStage route removal
 * through CronProvisionerFactory / QueueProvisionerFactory using the
 * ORIGINAL install snapshot (cron_line for cPanel, cron_path/config_path
 * for VPS) resolved from the immutable installation log — never rebuilt by
 * guesswork. cPanel with no recorded cron_line skips safely.
 */
class RemoveSchedulerQueueStagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;

        parent::setUp();
    }

    private function makeDeletion(string $serverType = 'fake', array $stageOutputs = []): CustomerDeletion
    {
        $server = CustomerRemoteServer::create([
            'label' => 'S', 'type' => $serverType, 'host' => 'box.example.test',
            'port' => 22, 'username' => 'johndoe', 'auth_method' => 'password',
            'password_encrypted' => (new CredentialVault())->encrypt('the-password'),
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'complete',
            'customer_company'  => 'Cron Del Co',
            'customer_email'    => 'crondel@example.com',
            'customer_domain'   => 'crondel.example.com',
            'request_payload'   => [
                'company' => 'Cron Del Co', 'owner_name' => 'Owner', 'email' => 'crondel@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'crondel.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        foreach ($stageOutputs as $stage => $output) {
            CustomerInstallationLog::create([
                'installation_id' => $installation->id,
                'stage'           => $stage,
                'event'           => 'completed',
                'message'         => 'done',
                'output'          => $output,
                'created_at'      => now(),
            ]);
        }

        return CustomerDeletion::create([
            'installation_id' => $installation->id,
            'initiated_by'    => 1,
            'policy'          => 'never_delete_backup',
            'status'          => 'running',
        ]);
    }

    private function cronFactory(): CronProvisionerFactory
    {
        return new CronProvisionerFactory(
            new VpsCronProvisioner(),
            new CpanelCronProvisioner(new CpanelCronLine(new CpanelUapiClient(new CredentialVault()))),
        );
    }

    private function queueFactory(): QueueProvisionerFactory
    {
        return new QueueProvisionerFactory(
            new VpsQueueProvisioner(),
            new CpanelQueueProvisioner(new CpanelCronLine(new CpanelUapiClient(new CredentialVault()))),
        );
    }

    // ─── Scheduler ────────────────────────────────────────────────────────────

    public function test_vps_scheduler_removal_falls_back_to_the_legacy_cron_path(): void
    {
        $driver = new FakeRemoteDriver();
        $deletion = $this->makeDeletion(); // nothing recorded by the install
        $ctx = DeleteContext::fromDeletion($deletion, $driver);

        $result = (new RemoveSchedulerStage($this->cronFactory()))->execute($ctx);

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertFalse($result->skipped);
        $log = implode("\n", $driver->getCommandLog());
        $this->assertStringContainsString('rm -f', $log);
        $this->assertStringContainsString('/etc/cron.d/' . $ctx->slug, $log);
    }

    public function test_vps_scheduler_removal_prefers_the_recorded_cron_path(): void
    {
        $driver = new FakeRemoteDriver();
        $deletion = $this->makeDeletion('fake', [
            'CONFIGURE_SCHEDULER' => ['cron_path' => '/etc/cron.d/custom_recorded_path'],
        ]);
        $ctx = DeleteContext::fromDeletion($deletion, $driver);

        (new RemoveSchedulerStage($this->cronFactory()))->execute($ctx);

        $log = implode("\n", $driver->getCommandLog());
        $this->assertStringContainsString('/etc/cron.d/custom_recorded_path', $log);
    }

    public function test_cpanel_scheduler_removal_uses_the_recorded_cron_line_via_uapi(): void
    {
        Http::fake(['*Cron/remove_line' => Http::response(['status' => 1, 'data' => []], 200)]);

        $line = '* * * * * cd /home/johndoe/public_html && php artisan schedule:run';
        $driver = new FakeRemoteDriver(driverType: DriverType::CPANEL);
        $deletion = $this->makeDeletion('cpanel', [
            'CONFIGURE_SCHEDULER' => ['cron_line' => $line],
        ]);
        $ctx = DeleteContext::fromDeletion($deletion, $driver);

        $result = (new RemoveSchedulerStage($this->cronFactory()))->execute($ctx);

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertFalse($result->skipped);
        $this->assertSame([], $driver->getCommandLog(), 'cPanel cron removal must not touch /etc/cron.d');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/execute/Cron/remove_line')
            && $request['line'] === $line);
    }

    public function test_cpanel_scheduler_removal_skips_safely_without_a_recorded_cron_line(): void
    {
        Http::fake();

        $driver = new FakeRemoteDriver(driverType: DriverType::CPANEL);
        $deletion = $this->makeDeletion('cpanel'); // no CONFIGURE_SCHEDULER record
        $ctx = DeleteContext::fromDeletion($deletion, $driver);

        $result = (new RemoveSchedulerStage($this->cronFactory()))->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertTrue($result->skipped, 'a cron_line must never be reconstructed by guesswork');
        Http::assertNothingSent();
        $this->assertSame([], $driver->getCommandLog());
    }

    // ─── Queue ────────────────────────────────────────────────────────────────

    public function test_vps_queue_removal_falls_back_to_the_legacy_supervisor_config(): void
    {
        $driver = new FakeRemoteDriver();
        $deletion = $this->makeDeletion();
        $ctx = DeleteContext::fromDeletion($deletion, $driver);

        $result = (new RemoveQueueStage($this->queueFactory()))->execute($ctx);

        $this->assertTrue($result->success, $result->error ?? '');
        $log = $driver->getCommandLog();
        $joined = implode("\n", $log);
        $this->assertStringContainsString("/etc/supervisor/conf.d/{$ctx->slug}_worker.conf", $joined);
        $this->assertStringContainsString('supervisorctl reread', $joined);
        $this->assertStringContainsString($ctx->supervisorLogPath, $joined, 'the worker log file is still cleaned up');
    }

    public function test_cpanel_queue_removal_uses_the_recorded_cron_line_via_uapi(): void
    {
        Http::fake(['*Cron/remove_line' => Http::response(['status' => 1, 'data' => []], 200)]);

        $line = '* * * * * cd /home/johndoe/public_html && php artisan queue:work --stop-when-empty --max-time=50';
        $driver = new FakeRemoteDriver(driverType: DriverType::CPANEL);
        $deletion = $this->makeDeletion('cpanel', [
            'CONFIGURE_QUEUE' => ['cron_line' => $line],
        ]);
        $ctx = DeleteContext::fromDeletion($deletion, $driver);

        $result = (new RemoveQueueStage($this->queueFactory()))->execute($ctx);

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertSame([], $driver->getCommandLog(), 'no Supervisor commands may run on cPanel');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/execute/Cron/remove_line')
            && $request['line'] === $line);
    }

    public function test_cpanel_queue_removal_skips_safely_without_a_recorded_cron_line(): void
    {
        Http::fake();

        $driver = new FakeRemoteDriver(driverType: DriverType::CPANEL);
        $deletion = $this->makeDeletion('cpanel');
        $ctx = DeleteContext::fromDeletion($deletion, $driver);

        $result = (new RemoveQueueStage($this->queueFactory()))->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertTrue($result->skipped);
        Http::assertNothingSent();
        $this->assertSame([], $driver->getCommandLog());
    }
}
