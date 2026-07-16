<?php

namespace Tests\Unit\CustomerInstallation\Ssl;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use App\Services\CustomerInstallation\Ssl\CpanelSslProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 15-B1 — CpanelSslProvisioner exercises the UAPI SSL module calls
 * over Http::fake(), the same pattern CpanelDatabaseProvisionerTest uses for
 * the Mysql module. See CpanelSslProvisioner's docblock: the exact UAPI
 * function names (`SSL::start_autossl_check`, `SSL::list_certs`) are this
 * implementation's best-documented understanding, not verified against a
 * live cPanel account — these tests verify THIS CLASS'S behavior (correct
 * request shape, graceful degradation on any failure), not that cPanel's
 * real API matches these names.
 */
class CpanelSslProvisionerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function context(CustomerRemoteServer $server, string $domain = 'cp.example.com'): InstallationContext
    {
        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Cpanel SSL Co',
            'customer_email'    => 'cpssl@example.com',
            'customer_domain'   => $domain,
            'request_payload'   => [
                'company' => 'Cpanel SSL Co', 'owner_name' => 'Owner', 'email' => 'cpssl@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => $domain, 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => true, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);
        $driver = new CpanelDeploymentDriver($server, new CredentialVault());

        return new InstallationContext($installation, $request, $driver);
    }

    private function passwordServer(): CustomerRemoteServer
    {
        $vault = new CredentialVault();

        return CustomerRemoteServer::create([
            'label' => 'cPanel', 'type' => 'cpanel', 'host' => 'box106.parsvds.com',
            'port' => 22, 'username' => 'johndoe', 'auth_method' => 'password',
            'password_encrypted' => $vault->encrypt('the-account-password'),
        ]);
    }

    private function provisioner(): CpanelSslProvisioner
    {
        return new CpanelSslProvisioner(new CpanelUapiClient(new CredentialVault()));
    }

    public function test_issue_certificate_triggers_autossl_check_via_uapi(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);
        $ctx = $this->context($this->passwordServer());

        $result = $this->provisioner()->issueCertificate($ctx, 'cp.example.com');

        $this->assertTrue($result->success);
        $this->assertSame('issued', $result->status);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/execute/SSL/start_autossl_check')
                && $request['domain'] === 'cp.example.com'
                && $request->hasHeader('Authorization', 'cpanel johndoe:the-account-password');
        });
    }

    public function test_issue_certificate_never_downloads_or_returns_key_material(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);
        $ctx = $this->context($this->passwordServer());

        $result = $this->provisioner()->issueCertificate($ctx, 'cp.example.com');

        $this->assertStringNotContainsString('BEGIN', (string) $result->certPath);
        $this->assertStringNotContainsString('BEGIN', (string) $result->keyPath);
    }

    public function test_issue_certificate_degrades_gracefully_when_autossl_is_not_enabled(): void
    {
        Http::fake(['*' => Http::response(['status' => 0, 'errors' => ['AutoSSL is not enabled for this account']], 200)]);
        $ctx = $this->context($this->passwordServer());

        $result = $this->provisioner()->issueCertificate($ctx, 'cp.example.com');

        $this->assertFalse($result->success);
        $this->assertSame('skipped', $result->status);
        $this->assertStringContainsString('AutoSSL', $result->message);
    }

    public function test_issue_certificate_degrades_gracefully_on_unknown_uapi_function(): void
    {
        // Simulates the exact risk flagged in this class's docblock: the
        // guessed function name is wrong for this cPanel version.
        Http::fake(['*' => Http::response(['status' => 0, 'errors' => ['Unknown UAPI function']], 200)]);
        $ctx = $this->context($this->passwordServer());

        $result = $this->provisioner()->issueCertificate($ctx, 'cp.example.com');

        $this->assertFalse($result->success);
        $this->assertSame('skipped', $result->status);
    }

    public function test_issue_certificate_degrades_gracefully_on_http_transport_failure(): void
    {
        Http::fake(['*' => Http::response('Gateway Timeout', 504)]);
        $ctx = $this->context($this->passwordServer());

        $result = $this->provisioner()->issueCertificate($ctx, 'cp.example.com');

        $this->assertFalse($result->success);
        $this->assertSame('skipped', $result->status);
    }

    public function test_check_certificate_reports_active_when_domain_is_listed(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 1,
            'data'   => [
                ['domains' => ['cp.example.com'], 'not_after' => '2026-09-07'],
            ],
        ], 200)]);
        $ctx = $this->context($this->passwordServer());

        $status = $this->provisioner()->checkCertificate($ctx, 'cp.example.com');

        $this->assertTrue($status->active);
        $this->assertSame('cpanel_autossl', $status->provider);
        $this->assertSame('2026-09-07', $status->expiresAt);
    }

    public function test_check_certificate_reports_none_when_domain_is_not_listed(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);
        $ctx = $this->context($this->passwordServer());

        $status = $this->provisioner()->checkCertificate($ctx, 'cp.example.com');

        $this->assertFalse($status->active);
        $this->assertSame('none', $status->provider);
    }

    public function test_check_certificate_never_throws_on_uapi_failure(): void
    {
        Http::fake(['*' => Http::response(['status' => 0, 'errors' => ['boom']], 200)]);
        $ctx = $this->context($this->passwordServer());

        $status = $this->provisioner()->checkCertificate($ctx, 'cp.example.com');

        $this->assertFalse($status->active);
        $this->assertSame('unknown', $status->provider);
    }

    public function test_no_whm_or_root_credential_is_ever_used(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);
        $ctx = $this->context($this->passwordServer());

        $this->provisioner()->issueCertificate($ctx, 'cp.example.com');

        // Only the cPanel account credential header shape (Phase 14-C/14-F2's
        // existing "cpanel {username}:{credential}" convention) is ever
        // used — never a WHM API token or a "root" username.
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'cpanel johndoe:the-account-password'));
    }
}
