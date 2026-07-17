<?php

namespace Tests\Unit\CustomerInstallation\Provisioning;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver;
use App\Services\CustomerInstallation\Provisioning\CpanelCronLine;
use App\Services\CustomerInstallation\Provisioning\CpanelCronProvisioner;
use App\Services\CustomerInstallation\Provisioning\CpanelQueueProvisioner;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 15-F — Part 4/7: originally documented a real edge case (reported,
 * NOT fixed — Phase 15-F was audit-only). Unlike ProvisionDomainStage/
 * CpanelDomainProvisioner (which checked for an existing entry before
 * creating one), CpanelCronLine::install() — shared by both
 * CpanelCronProvisioner (scheduler) and CpanelQueueProvisioner (queue
 * worker) — called UAPI Cron::add_line unconditionally, with no "does this
 * exact line already exist" pre-check.
 *
 * Consequence: if CONFIGURE_SCHEDULER or CONFIGURE_QUEUE's remote UAPI call
 * succeeded but the process crashed before CustomerInstallationOrchestrator
 * recorded the stage complete, resuming re-ran the stage and added a
 * SECOND, duplicate cron line — the job then ran twice per minute.
 *
 * Phase 15-G — Part 2: fixed. CpanelCronLine::install() now checks
 * Cron::list_lines for the exact command before calling add_line. These
 * tests are updated in place (per the file's own prior comment) to verify
 * the fix: only ONE add_line call total across a first-attempt-then-resume
 * pair, with the resumed call reporting already_existed=true instead of
 * silently duplicating.
 */
class CpanelCronDuplicationOnResumeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
        config(['customer_installation.cpanel.uapi_retry_delay' => 0]);
    }

    private function context(): InstallationContext
    {
        $vault  = new CredentialVault();
        $server = CustomerRemoteServer::create([
            'label' => 'cPanel', 'type' => 'cpanel', 'host' => 'box106.parsvds.com',
            'port' => 22, 'username' => 'johndoe', 'auth_method' => 'password',
            'password_encrypted' => $vault->encrypt('the-account-password'),
        ]);
        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Cron Dup Co',
            'customer_email'    => 'crondup@example.com',
            'customer_domain'   => 'crondup.example.com',
            'request_payload'   => [
                'company' => 'Cron Dup Co', 'owner_name' => 'Owner', 'email' => 'crondup@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'crondup.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);
        $request = CustomerInstallRequest::fromArray($installation->request_payload);
        $driver  = new CpanelDeploymentDriver($server, $vault);

        return new InstallationContext($installation, $request, $driver);
    }

    public function test_resuming_configure_scheduler_after_a_crash_does_not_add_a_duplicate_cron_line(): void
    {
        $ctx = $this->context();

        // Determined by WebRootResolver's tier-3 sharedHostingDefault()
        // fallback (no PROVISION_DOMAIN output/log, and DomainInfo::domains_data
        // below reports no matching main/sub/addon domain) — asserted below
        // rather than assumed, since $ctx->webRoot() itself makes a live UAPI
        // call that must be mocked up front (it would otherwise fall through
        // to a real network attempt, retried by the Part 1 retry loop).
        Http::fake([
            '*DomainInfo/domains_data*' => Http::response(['status' => 1, 'data' => [
                'main_domain' => 'johndoe.example.com',
                'domains'     => [],
            ]], 200),
        ]);
        $webRoot = $ctx->webRoot();
        $this->assertSame('/home/johndoe/public_html', $webRoot);
        $command = "cd {$webRoot} && php artisan schedule:run >> /dev/null 2>&1";

        Http::fake([
            '*Cron/list_lines*' => Http::sequence()
                ->push(['status' => 1, 'data' => []], 200)
                ->push(['status' => 1, 'data' => [['command' => $command]]], 200),
            '*Cron/add_line*' => Http::response(['status' => 1, 'data' => []], 200),
        ]);

        $provisioner = new CpanelCronProvisioner(new CpanelCronLine(new CpanelUapiClient(new CredentialVault())));

        // First attempt (before the simulated crash): no matching line yet -> adds one.
        $first = $provisioner->installScheduler($ctx);
        $this->assertTrue($first->installed);
        $this->assertArrayNotHasKey('already_existed', $first->output);

        // Resume: the stage re-runs because the crash happened after the
        // remote UAPI call succeeded but before CustomerInstallationOrchestrator
        // recorded CONFIGURE_SCHEDULER as complete. The line now already
        // exists, so this must NOT call add_line a second time.
        $second = $provisioner->installScheduler($ctx);
        $this->assertTrue($second->installed);
        $this->assertTrue($second->output['already_existed'] ?? false);

        Http::assertSentCount(3); // list_lines + add_line + list_lines — never a second add_line
        Http::assertSent(fn ($request) => str_contains($request->url(), 'add_line'));
        $this->assertSame(
            1,
            Http::recorded(fn ($request) => str_contains($request->url(), 'add_line'))->count(),
            'add_line must be called exactly once across the first attempt and the resume'
        );
    }

    public function test_resuming_configure_queue_after_a_crash_does_not_add_a_duplicate_cron_line(): void
    {
        $ctx = $this->context();

        Http::fake([
            '*DomainInfo/domains_data*' => Http::response(['status' => 1, 'data' => [
                'main_domain' => 'johndoe.example.com',
                'domains'     => [],
            ]], 200),
        ]);
        $webRoot = $ctx->webRoot();
        $command = "cd {$webRoot} && php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1";

        Http::fake([
            '*Cron/list_lines*' => Http::sequence()
                ->push(['status' => 1, 'data' => []], 200)
                ->push(['status' => 1, 'data' => [['command' => $command]]], 200),
            '*Cron/add_line*' => Http::response(['status' => 1, 'data' => []], 200),
        ]);

        $provisioner = new CpanelQueueProvisioner(new CpanelCronLine(new CpanelUapiClient(new CredentialVault())));

        $first = $provisioner->installWorker($ctx);
        $this->assertTrue($first->installed);

        $second = $provisioner->installWorker($ctx);
        $this->assertTrue($second->installed);
        $this->assertTrue($second->output['already_existed'] ?? false);

        $this->assertSame(
            1,
            Http::recorded(fn ($request) => str_contains($request->url(), 'add_line'))->count(),
            'add_line must be called exactly once across the first attempt and the resume'
        );
    }
}
