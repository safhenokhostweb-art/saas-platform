<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Infrastructure\DnsProviderSetting;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Enums\DriverType;
use App\Services\CustomerInstallation\Ssl\ArvanCdnDomainDecider;
use App\Services\CustomerInstallation\Stages\ValidateServerStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 15-D — Part 6: the certbot preflight check runs as part of
 * VALIDATE_SERVER (the very first stage) instead of only surfacing at
 * IssueSslStage (the near-last stage), so a missing certbot fails fast
 * before any upload/extract/database work happens.
 */
class CertbotPreflightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function stage(): ValidateServerStage
    {
        return new ValidateServerStage(new ArvanCdnDomainDecider());
    }

    private function context(FakeRemoteDriver $driver, bool $sslEnabled, string $domain = 'example.com'): InstallationContext
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Fake', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Certbot Preflight Co',
            'customer_email'    => 'preflight@example.com',
            'customer_domain'   => $domain,
            'request_payload'   => [
                'company' => 'Certbot Preflight Co', 'owner_name' => 'Owner', 'email' => 'preflight@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => $domain, 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => $sslEnabled, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);

        return new InstallationContext($installation, $request, $driver);
    }

    public function test_fails_fast_when_ssl_requested_and_certbot_is_missing_on_vps(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('command -v', 0, 'missing');
        $ctx = $this->context($driver, sslEnabled: true);

        $result = $this->stage()->execute($ctx);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('certbot is not installed', $result->error);
    }

    public function test_succeeds_when_ssl_requested_and_certbot_is_present_on_vps(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('command -v', 0, 'present');
        $ctx = $this->context($driver, sslEnabled: true);

        $result = $this->stage()->execute($ctx);

        $this->assertTrue($result->success);
    }

    public function test_skipped_when_ssl_is_not_requested(): void
    {
        $driver = new FakeRemoteDriver();
        // No canned response for 'command -v' — if the stage called it, the
        // FakeRemoteDriver default (empty stdout) would fail the "present"
        // check and the stage would incorrectly fail. Asserting success here
        // proves the preflight check was skipped entirely, not accidentally
        // passed.
        $ctx = $this->context($driver, sslEnabled: false);

        $result = $this->stage()->execute($ctx);

        $this->assertTrue($result->success);
    }

    public function test_skipped_when_domain_is_arvan_managed(): void
    {
        DnsProviderSetting::create([
            'provider' => 'arvan', 'root_domain' => 'safhenokhost.ir',
            'is_active' => true, 'cloud_proxy' => true,
            'api_token_encrypted' => (new CredentialVault())->encrypt('token'),
        ]);

        $driver = new FakeRemoteDriver();
        $ctx = $this->context($driver, sslEnabled: true, domain: 'tenant1.safhenokhost.ir');

        $result = $this->stage()->execute($ctx);

        $this->assertTrue($result->success, 'Arvan terminates SSL at its edge — certbot is irrelevant for this domain');
    }

    public function test_skipped_for_cpanel_driver(): void
    {
        // FakeRemoteDriver with an explicit cpanel driverType — not a real
        // CpanelDeploymentDriver — so this test never attempts a live SSH
        // connection; only driverType()->isVpsFamily() is under test here.
        $driver = new FakeRemoteDriver(driverType: DriverType::CPANEL);
        $server = CustomerRemoteServer::create([
            'label' => 'cPanel', 'type' => 'cpanel', 'host' => 'box.example.test',
            'port' => 22, 'username' => 'johndoe', 'auth_method' => 'password',
            'password_encrypted' => (new CredentialVault())->encrypt('pw'),
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Cpanel Preflight Co',
            'customer_email'    => 'cppreflight@example.com',
            'customer_domain'   => 'cp.example.com',
            'request_payload'   => [
                'company' => 'Cpanel Preflight Co', 'owner_name' => 'Owner', 'email' => 'cppreflight@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'cp.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => true, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);
        $request = CustomerInstallRequest::fromArray($installation->request_payload);
        $ctx     = new InstallationContext($installation, $request, $driver);

        $result = $this->stage()->execute($ctx);

        $this->assertTrue($result->success, 'cPanel uses AutoSSL, not certbot — the preflight check is not applicable');
    }
}
