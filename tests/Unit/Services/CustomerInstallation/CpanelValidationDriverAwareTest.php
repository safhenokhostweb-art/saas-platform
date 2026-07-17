<?php

namespace Tests\Unit\Services\CustomerInstallation;

use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationLog;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\DriverCapabilities;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Enums\DriverType;
use App\Services\CustomerInstallation\PostInstallValidationService;
use App\Services\CustomerInstallation\Ssl\ArvanCdnDomainDecider;
use App\Services\CustomerInstallation\Ssl\CpanelSslProvisioner;
use App\Services\CustomerInstallation\Ssl\SslProvisionerFactory;
use App\Services\CustomerInstallation\Ssl\VpsSslProvisioner;
use App\Services\CustomerInstallation\Stages\ValidatePermissionsStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 15-G — Part 4/7: PostInstallValidationService::checkQueue()/
 * checkScheduler() and ValidatePermissionsStage previously ran unconditional
 * VPS-shaped probes (supervisorctl, /etc/cron.d, /var/www) against every
 * driver, including cPanel — producing false WARNs for queue/scheduler, and
 * (the more severe finding made while implementing this part, not by the
 * Phase 15-F audit) a hard StageResult::failure() at VALIDATE_PERMISSIONS on
 * every single cPanel install, since a shared-hosting SSH account almost
 * never has write access to /var/www.
 */
class CpanelValidationDriverAwareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
        config(['customer_installation.cpanel.uapi_retry_delay' => 0]);

        // PostInstallValidationService::runAll() calls $ctx->webRoot() up
        // front, which (for a CPANEL-typed driver) makes a live
        // DomainInfo::domains_data UAPI call via WebRootResolver's tier 2 —
        // unrelated to what these tests actually assert, but left unmocked
        // it falls through to a real network attempt against the fake host,
        // retried by the Part 1 retry loop. Faked here so it resolves
        // instantly to the tier-3 deterministic default instead.
        Http::fake(['*DomainInfo/domains_data*' => Http::response(['status' => 1, 'data' => [
            'main_domain' => 'johndoe.example.com',
            'domains'     => [],
        ]], 200)]);
    }

    private function context(FakeRemoteDriver $driver): InstallationContext
    {
        $server = CustomerRemoteServer::create([
            'label' => 'cPanel', 'type' => 'cpanel', 'host' => 'box106.parsvds.com',
            'port' => 22, 'username' => 'johndoe', 'auth_method' => 'password',
            'password_encrypted' => (new CredentialVault())->encrypt('the-account-password'),
        ]);
        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Validation Co',
            'customer_email'    => 'validate@example.com',
            'customer_domain'   => 'shop.johndoe.example.com',
            'request_payload'   => [
                'company' => 'Validation Co', 'owner_name' => 'Owner', 'email' => 'validate@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'shop.johndoe.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);
        $request = CustomerInstallRequest::fromArray($installation->request_payload);

        return new InstallationContext($installation, $request, $driver);
    }

    private function service(): PostInstallValidationService
    {
        return new PostInstallValidationService(
            new SslProvisionerFactory(new VpsSslProvisioner(), $this->createStub(CpanelSslProvisioner::class)),
            new ArvanCdnDomainDecider(),
        );
    }

    // ─── ValidatePermissionsStage ──────────────────────────────────────────

    public function test_cpanel_permissions_check_never_probes_var_www_and_does_not_hard_fail(): void
    {
        $driver = new FakeRemoteDriver(DriverCapabilities::forCpanel(), DriverType::CPANEL);
        $driver->addResponse("echo 'test'", 0, 'ok');
        $ctx = $this->context($driver);

        $result = (new ValidatePermissionsStage())->execute($ctx);

        $this->assertTrue(
            $result->success,
            'Phase 15-G Part 4: probing /var/www on a cPanel account must never hard-fail VALIDATE_PERMISSIONS'
        );
        $this->assertFalse(
            collect($driver->getCommandLog())->contains(fn ($c) => str_contains($c, '/var/www')),
            'cPanel must never probe the VPS-only /var/www path'
        );
    }

    public function test_cpanel_permissions_check_never_probes_supervisor_or_cron_d_and_reports_no_warnings(): void
    {
        $driver = new FakeRemoteDriver(DriverCapabilities::forCpanel(), DriverType::CPANEL);
        $driver->addResponse("echo 'test'", 0, 'ok');
        // Deliberately no canned response for /etc/supervisor or /etc/cron.d —
        // if the stage probed them here, FakeRemoteDriver's default empty
        // response would produce a false WARN, proving the guard works.
        $ctx = $this->context($driver);

        $result = (new ValidatePermissionsStage())->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertSame([], $result->output['warnings']);
        $this->assertTrue($result->output['supervisor_writable']);
        $this->assertTrue($result->output['cron_writable']);
    }

    public function test_vps_permissions_check_behavior_is_unchanged(): void
    {
        $driver = new FakeRemoteDriver(DriverCapabilities::forVps(), DriverType::SSH);
        $driver->addResponse("echo 'test'", 0, 'ok');
        // /etc/supervisor and /etc/cron.d left unanswered -> default empty
        // response -> both should be reported not-writable, as before.
        $ctx = $this->context($driver);

        $result = (new ValidatePermissionsStage())->execute($ctx);

        $this->assertTrue($result->success);
        $this->assertTrue(collect($driver->getCommandLog())->contains(fn ($c) => str_contains($c, '/var/www')));
        $this->assertFalse($result->output['supervisor_writable']);
        $this->assertFalse($result->output['cron_writable']);
        $this->assertCount(2, $result->output['warnings']);
    }

    // ─── PostInstallValidationService::checkQueue()/checkScheduler() ───────

    public function test_cpanel_queue_and_scheduler_checks_pass_when_the_provisioning_stage_recorded_success(): void
    {
        $ctx = $this->context(new FakeRemoteDriver(DriverCapabilities::forCpanel(), DriverType::CPANEL));
        $ctx->recordOutput('CONFIGURE_QUEUE', ['cron_line' => '* * * * * php artisan queue:work', 'already_existed' => false]);
        $ctx->recordOutput('CONFIGURE_SCHEDULER', ['cron_line' => '* * * * * php artisan schedule:run', 'already_existed' => false]);

        $checks = $this->service()->runAll($ctx);

        $this->assertSame('PASS', $checks['queue']['status']);
        $this->assertSame('PASS', $checks['scheduler']['status']);
    }

    public function test_cpanel_queue_and_scheduler_checks_warn_when_the_provisioning_stage_was_skipped(): void
    {
        $ctx = $this->context(new FakeRemoteDriver(DriverCapabilities::forCpanel(), DriverType::CPANEL));
        $ctx->recordOutput('CONFIGURE_QUEUE', ['status' => 'skipped', 'message' => 'cPanel UAPI Cron::add_line (queue worker) failed']);
        $ctx->recordOutput('CONFIGURE_SCHEDULER', ['status' => 'skipped', 'message' => 'cPanel UAPI Cron::add_line (scheduler) failed']);

        $checks = $this->service()->runAll($ctx);

        $this->assertSame('WARN', $checks['queue']['status']);
        $this->assertSame('WARN', $checks['scheduler']['status']);
    }

    public function test_cpanel_queue_check_never_probes_supervisorctl(): void
    {
        $driver = new FakeRemoteDriver(DriverCapabilities::forCpanel(), DriverType::CPANEL);
        $ctx = $this->context($driver);
        $ctx->recordOutput('CONFIGURE_QUEUE', ['cron_line' => '* * * * * php artisan queue:work']);
        $ctx->recordOutput('CONFIGURE_SCHEDULER', ['cron_line' => '* * * * * php artisan schedule:run']);

        $this->service()->runAll($ctx);

        $this->assertFalse(
            collect($driver->getCommandLog())->contains(fn ($c) => str_contains($c, 'supervisorctl') || str_contains($c, '/etc/cron.d')),
            'cPanel must never probe supervisorctl or /etc/cron.d — those checks are VPS-only'
        );
    }

    public function test_cpanel_queue_check_falls_back_to_the_persisted_log_when_the_in_memory_output_is_empty(): void
    {
        $ctx = $this->context(new FakeRemoteDriver(DriverCapabilities::forCpanel(), DriverType::CPANEL));
        // Simulates a resumed run: CONFIGURE_QUEUE completed in an earlier
        // process, so this run's in-memory $ctx->stageOutputs never saw it.
        CustomerInstallationLog::create([
            'installation_id' => $ctx->installation->id,
            'stage'           => 'CONFIGURE_QUEUE',
            'event'           => 'completed',
            'message'         => 'ok',
            'output'          => ['cron_line' => '* * * * * php artisan queue:work'],
            'duration_ms'     => 10,
            'created_at'      => now(),
        ]);

        $checks = $this->service()->runAll($ctx);

        $this->assertSame('PASS', $checks['queue']['status']);
    }

    public function test_vps_queue_check_still_uses_supervisorctl(): void
    {
        $driver = new FakeRemoteDriver(DriverCapabilities::forVps(), DriverType::SSH);
        $ctx = $this->context($driver);
        $driver->addResponse('supervisorctl status', 0, 'worker RUNNING pid 123, uptime 0:01:00');
        $driver->addResponse('/etc/cron.d', 0, 'found');

        $checks = $this->service()->runAll($ctx);

        $this->assertSame('PASS', $checks['queue']['status']);
        $this->assertSame('PASS', $checks['scheduler']['status']);
        $this->assertTrue(collect($driver->getCommandLog())->contains(fn ($c) => str_contains($c, 'supervisorctl')));
    }
}
