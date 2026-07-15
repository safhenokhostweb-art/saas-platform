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
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CpanelCronProvisionerTest extends TestCase
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
            'customer_company'  => 'Cron Cpanel Co',
            'customer_email'    => 'croncpanel@example.com',
            'customer_domain'   => 'croncpanel.example.com',
            'request_payload'   => [
                'company' => 'Cron Cpanel Co', 'owner_name' => 'Owner', 'email' => 'croncpanel@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'croncpanel.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);
        $driver = new CpanelDeploymentDriver($server, $vault);

        return [new InstallationContext($installation, $request, $driver), $vault];
    }

    private function provisioner(CredentialVault $vault): CpanelCronProvisioner
    {
        return new CpanelCronProvisioner(new CpanelCronLine(new CpanelUapiClient($vault)));
    }

    public function test_install_scheduler_calls_cron_add_line_with_the_schedule_run_command(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);
        [$ctx, $vault] = $this->context();

        $result = $this->provisioner($vault)->installScheduler($ctx);

        $this->assertTrue($result->installed);
        $this->assertStringContainsString('php artisan schedule:run', $result->snapshot['cron_line']);

        Http::assertSent(function ($request) use ($ctx) {
            return str_ends_with($request->url(), '/execute/Cron/add_line')
                && str_contains($request['command'], 'schedule:run')
                && str_contains($request['command'], $ctx->webRoot())
                && $request['minute'] === '*'
                && $request->hasHeader('Authorization', 'cpanel johndoe:the-password');
        });
    }

    public function test_install_scheduler_skips_gracefully_on_uapi_failure(): void
    {
        Http::fake(['*' => Http::response(['status' => 0, 'errors' => ['cron limit reached']], 200)]);
        [$ctx, $vault] = $this->context();

        $result = $this->provisioner($vault)->installScheduler($ctx);

        $this->assertFalse($result->installed);
        $this->assertSame('skipped', $result->output['status']);
        $this->assertStringContainsString('cron limit reached', $result->output['message']);
    }

    public function test_remove_scheduler_calls_remove_line_with_the_exact_installed_line(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);
        [$ctx, $vault] = $this->context();

        $this->provisioner($vault)->removeScheduler($ctx, ['cron_line' => '* * * * * php artisan schedule:run']);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/execute/Cron/remove_line')
                && $request['line'] === '* * * * * php artisan schedule:run';
        });
    }

    public function test_remove_scheduler_never_throws_on_uapi_failure(): void
    {
        Http::fake(['*' => Http::response(['status' => 0, 'errors' => ['not found']], 200)]);
        [$ctx, $vault] = $this->context();

        $this->provisioner($vault)->removeScheduler($ctx, ['cron_line' => '* * * * * anything']);

        $this->addToAssertionCount(1); // reaching here means it didn't throw
    }
}
