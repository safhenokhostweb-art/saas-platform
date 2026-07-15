<?php

namespace Tests\Unit\CustomerInstallation\Provisioning;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver;
use App\Services\CustomerInstallation\Provisioning\CpanelCronLine;
use App\Services\CustomerInstallation\Provisioning\CpanelQueueProvisioner;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 14-F3 — cPanel has no Supervisor, so the queue worker is a
 * cron-based `queue:work --stop-when-empty` job instead, installed through
 * the same UAPI cron mechanism as the scheduler.
 */
class CpanelQueueProvisionerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function context(): array
    {
        $vault = new CredentialVault();
        $server = CustomerRemoteServer::create([
            'label' => 'cPanel', 'type' => 'cpanel', 'host' => 'box106.parsvds.com',
            'port' => 22, 'username' => 'johndoe', 'auth_method' => 'password',
            'password_encrypted' => $vault->encrypt('the-password'),
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Queue Cpanel Co',
            'customer_email'    => 'queuecpanel@example.com',
            'customer_domain'   => 'queuecpanel.example.com',
            'request_payload'   => [
                'company' => 'Queue Cpanel Co', 'owner_name' => 'Owner', 'email' => 'queuecpanel@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'queuecpanel.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);
        $driver = new CpanelDeploymentDriver($server, $vault);

        return [new InstallationContext($installation, $request, $driver), $vault];
    }

    private function provisioner(CredentialVault $vault): CpanelQueueProvisioner
    {
        return new CpanelQueueProvisioner(new CpanelCronLine(new CpanelUapiClient($vault)));
    }

    public function test_install_worker_uses_stop_when_empty_instead_of_a_persistent_worker(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);
        [$ctx, $vault] = $this->context();

        $result = $this->provisioner($vault)->installWorker($ctx);

        $this->assertTrue($result->installed);
        $this->assertStringContainsString('queue:work', $result->snapshot['cron_line']);
        $this->assertStringContainsString('--stop-when-empty', $result->snapshot['cron_line']);
        $this->assertStringContainsString('--max-time=50', $result->snapshot['cron_line']);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/execute/Cron/add_line')
                && str_contains($request['command'], '--stop-when-empty')
                && str_contains($request['command'], '--max-time=50');
        });
    }

    public function test_install_worker_skips_gracefully_on_uapi_failure(): void
    {
        Http::fake(['*' => Http::response(['status' => 0, 'errors' => ['cron limit reached']], 200)]);
        [$ctx, $vault] = $this->context();

        $result = $this->provisioner($vault)->installWorker($ctx);

        $this->assertFalse($result->installed);
        $this->assertSame('skipped', $result->output['status']);
    }

    public function test_remove_worker_calls_remove_line_and_never_throws(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);
        [$ctx, $vault] = $this->context();

        $this->provisioner($vault)->removeWorker($ctx, ['cron_line' => '* * * * * php artisan queue:work --stop-when-empty --max-time=50']);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/execute/Cron/remove_line'));
    }
}
