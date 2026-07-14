<?php

namespace Tests\Unit\CustomerInstallation\Provisioning;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver;
use App\Services\CustomerInstallation\Provisioning\CpanelCronLine;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 14-G1 — CpanelCronLine::install()/remove() used to catch only
 * RuntimeException (thrown by CpanelUapiClient for a bad HTTP status or a
 * UAPI-level error). A genuine transport-level failure — a UAPI request
 * timing out, DNS failure, TLS handshake failure — throws
 * Illuminate\Http\Client\ConnectionException instead, which does NOT extend
 * RuntimeException. Both CONFIGURE_SCHEDULER and CONFIGURE_QUEUE are
 * optional stages (isCapabilityRequiredToProceed() === false) specifically
 * so a missing/unreliable capability degrades to a skip rather than failing
 * the whole installation — before this fix, a UAPI timeout broke that
 * contract and aborted the entire install + triggered a rollback instead.
 */
class CpanelCronLineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Set before parent::setUp() — that call boots a fresh Application
        // and config/installer.php reads this env var at that moment. See
        // GenerateEnvironmentStageCredentialTest for the full rationale.
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;

        parent::setUp();
    }

    private function context(): InstallationContext
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
            'customer_company'  => 'Cron Line Co',
            'customer_email'    => 'cronline@example.com',
            'customer_domain'   => 'cronline.example.com',
            'request_payload'   => [
                'company' => 'Cron Line Co', 'owner_name' => 'Owner', 'email' => 'cronline@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'cronline.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);
        $driver = new CpanelDeploymentDriver($server, $vault);

        return new InstallationContext($installation, $request, $driver);
    }

    private function cronLine(): CpanelCronLine
    {
        return new CpanelCronLine(new CpanelUapiClient(new CredentialVault()));
    }

    public function test_install_skips_gracefully_on_a_uapi_level_runtime_exception(): void
    {
        Http::fake(['*' => Http::response(['status' => 0, 'errors' => ['cron limit reached']], 200)]);

        $result = $this->cronLine()->install($this->context(), 'php artisan schedule:run', 'Scheduler');

        $this->assertFalse($result->installed);
        $this->assertSame('skipped', $result->output['status']);
        $this->assertStringContainsString('cron limit reached', $result->output['message']);
    }

    public function test_install_skips_gracefully_on_a_transport_connection_exception(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out after 30 seconds');
        });

        $result = $this->cronLine()->install($this->context(), 'php artisan schedule:run', 'Scheduler');

        $this->assertFalse($result->installed);
        $this->assertSame('skipped', $result->output['status']);
        $this->assertStringContainsString('Connection timed out', $result->output['message']);
    }

    public function test_remove_never_throws_on_a_transport_connection_exception(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out after 30 seconds');
        });

        $this->cronLine()->remove($this->context(), ['cron_line' => '* * * * * php artisan schedule:run']);

        $this->addToAssertionCount(1); // reaching here means it didn't throw
    }
}
