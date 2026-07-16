<?php

namespace Tests\Unit\CustomerInstallation\Ssl;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Ssl\VpsSslProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 15-B1 — VpsSslProvisioner exercises real Certbot invocation shape
 * (webroot mode) over the FakeRemoteDriver transport. No live certbot/ACME
 * server is contacted; command log assertions confirm the right command was
 * issued, and canned responses simulate certbot's observable effect (a
 * certificate file appearing on disk).
 */
class VpsSslProvisionerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function context(FakeRemoteDriver $driver, string $domain = 'example.com'): InstallationContext
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
            'customer_company'  => 'SSL Test Co',
            'customer_email'    => 'admin@example.com',
            'customer_domain'   => $domain,
            'request_payload'   => [
                'company' => 'SSL Test Co', 'owner_name' => 'Owner', 'email' => 'admin@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => $domain, 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => true, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);

        return new InstallationContext($installation, $request, $driver);
    }

    public function test_issue_certificate_skips_gracefully_when_certbot_is_not_installed(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('command -v', 0, 'missing');
        $ctx = $this->context($driver);

        $result = (new VpsSslProvisioner())->issueCertificate($ctx, 'example.com');

        $this->assertFalse($result->success);
        $this->assertSame('skipped', $result->status);
        $this->assertStringContainsString('certbot is not installed', $result->message);
        $this->assertNull($this->firstIndexContaining($driver->getCommandLog(), 'certonly'));
    }

    public function test_issue_certificate_runs_certbot_webroot_and_returns_issued_on_success(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('command -v', 0, 'present');
        $driver->addResponse('certonly', 0, 'Successfully received certificate');
        $driver->addResponse('test -f', 0, 'yes');
        $driver->addResponse('openssl x509', 0, 'notAfter=Sep  7 00:00:00 2026 GMT');
        $ctx = $this->context($driver);

        $result = (new VpsSslProvisioner())->issueCertificate($ctx, 'example.com');

        $this->assertTrue($result->success);
        $this->assertSame('issued', $result->status);
        $this->assertSame('/etc/letsencrypt/live/example.com/fullchain.pem', $result->certPath);
        $this->assertSame('/etc/letsencrypt/live/example.com/privkey.pem', $result->keyPath);

        $certbotCmd = $driver->getCommandLog()[$this->firstIndexContaining($driver->getCommandLog(), 'certonly')];
        $this->assertStringContainsString('--webroot', $certbotCmd);
        $this->assertStringContainsString('example.com', $certbotCmd);
        $this->assertStringContainsString('--non-interactive', $certbotCmd);
        $this->assertStringContainsString('admin@example.com', $certbotCmd);
    }

    public function test_issue_certificate_returns_failed_when_certificate_never_appears(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('command -v', 0, 'present');
        $driver->addResponse('certonly', 1, 'DNS problem: NXDOMAIN looking up A record');
        $driver->addResponse('test -f', 0, 'no');
        $ctx = $this->context($driver);

        $result = (new VpsSslProvisioner())->issueCertificate($ctx, 'example.com');

        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
        $this->assertStringContainsString('Certbot issuance failed', $result->message);
    }

    public function test_check_certificate_returns_inactive_when_no_file_exists(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('test -f', 0, 'no');
        $ctx = $this->context($driver);

        $status = (new VpsSslProvisioner())->checkCertificate($ctx, 'example.com');

        $this->assertFalse($status->active);
        $this->assertSame('none', $status->provider);
    }

    public function test_check_certificate_parses_expiry_date_from_openssl_output(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('test -f', 0, 'yes');
        $driver->addResponse('openssl x509', 0, 'notAfter=Sep  7 00:00:00 2026 GMT');
        $ctx = $this->context($driver);

        $status = (new VpsSslProvisioner())->checkCertificate($ctx, 'example.com');

        $this->assertTrue($status->active);
        $this->assertSame('letsencrypt', $status->provider);
        $this->assertSame('2026-09-07', $status->expiresAt);
    }

    public function test_check_certificate_never_throws_on_transport_failure(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->willFail('ssh connection reset');
        $ctx = $this->context($driver);

        $status = (new VpsSslProvisioner())->checkCertificate($ctx, 'example.com');

        $this->assertFalse($status->active);
        $this->assertSame('unknown', $status->provider);
    }

    public function test_renew_certificate_skips_gracefully_when_certbot_is_not_installed(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('command -v', 0, 'missing');
        $ctx = $this->context($driver);

        $result = (new VpsSslProvisioner())->renewCertificate($ctx, 'example.com');

        $this->assertFalse($result->success);
        $this->assertSame('skipped', $result->status);
    }

    public function test_certificate_output_never_leaks_key_material_even_if_certbot_prints_it(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('command -v', 0, 'present');
        $driver->addResponse('certonly', 1, "-----BEGIN PRIVATE KEY-----\nMIIEv...\n-----END PRIVATE KEY-----\nfailure");
        $driver->addResponse('test -f', 0, 'no');
        $ctx = $this->context($driver);

        $result = (new VpsSslProvisioner())->issueCertificate($ctx, 'example.com');

        $this->assertStringNotContainsString('BEGIN PRIVATE KEY', $result->message);
        $this->assertStringContainsString('[REDACTED KEY MATERIAL]', $result->message);
    }

    private function firstIndexContaining(array $log, string $needle): ?int
    {
        foreach ($log as $index => $command) {
            if (str_contains($command, $needle)) {
                return $index;
            }
        }

        return null;
    }
}
