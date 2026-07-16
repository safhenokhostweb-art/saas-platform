<?php

namespace Tests\Unit\CustomerInstallation\Domain;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Domain\CpanelDomainProvisioner;
use App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 15-E — Part 3: scenario-focused regression coverage distinct from
 * CpanelDomainProvisionerTest's happy-path/idempotency coverage — this file
 * documents exactly what happens for the operationally realistic failure
 * shapes an operator will actually hit on a live account: addon-domain
 * quota exhaustion and a duplicate-domain rejection. In both cases the
 * REAL cPanel error text must reach the operator verbatim (never swallowed
 * or replaced with a generic message) since CpanelUapiClient already
 * surfaces it faithfully — these tests lock that contract in.
 */
class CpanelDomainProvisionerRealResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function context(CustomerRemoteServer $server, string $domain): InstallationContext
    {
        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Real Response Co',
            'customer_email'    => 'realresponse@example.com',
            'customer_domain'   => $domain,
            'request_payload'   => [
                'company' => 'Real Response Co', 'owner_name' => 'Owner', 'email' => 'realresponse@example.com',
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

    private function server(): CustomerRemoteServer
    {
        $vault = new CredentialVault();

        return CustomerRemoteServer::create([
            'label' => 'cPanel', 'type' => 'cpanel', 'host' => 'box106.parsvds.com',
            'port' => 22, 'username' => 'johndoe', 'auth_method' => 'password',
            'password_encrypted' => $vault->encrypt('the-account-password'),
        ]);
    }

    private function provisioner(): CpanelDomainProvisioner
    {
        return new CpanelDomainProvisioner(new CpanelUapiClient(new CredentialVault()));
    }

    public function test_addon_domain_quota_exceeded_surfaces_the_real_cpanel_message_verbatim(): void
    {
        Http::fake([
            '*DomainInfo/domains_data*' => Http::response(['status' => 1, 'data' => [
                'main_domain' => 'johndoe.example.com',
                'domains'     => [],
            ]], 200),
            '*AddonDomain/addaddondomain*' => Http::response([
                'status' => 0,
                'errors' => ['Sorry, you are limited to 0 addon domain(s).'],
            ], 200),
        ]);
        $ctx = $this->context($this->server(), 'othercompany.com');

        $result = $this->provisioner()->createDomain($ctx, 'othercompany.com');

        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
        $this->assertStringContainsString('limited to 0 addon domain', $result->message);
    }

    public function test_duplicate_subdomain_creation_attempt_surfaces_the_real_cpanel_message(): void
    {
        Http::fake([
            '*DomainInfo/domains_data*' => Http::response(['status' => 1, 'data' => [
                'main_domain' => 'johndoe.example.com',
                'domains'     => [],
            ]], 200),
            '*SubDomain/addsubdomain*' => Http::response([
                'status' => 0,
                'errors' => ['Sub-domain already exists.'],
            ], 200),
        ]);
        $ctx = $this->context($this->server(), 'shop.johndoe.example.com');

        $result = $this->provisioner()->createDomain($ctx, 'shop.johndoe.example.com');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('already exists', $result->message);
    }

    /**
     * If DomainInfo::domains_data itself already lists the domain (the
     * account genuinely has it, just under a shape this parser didn't
     * expect to find via documentRootFor()'s normal lookup — e.g. a
     * differently-keyed response), the pipeline must never attempt to
     * recreate it blindly; a create attempt is only ever made when the
     * existence check found nothing.
     */
    public function test_domain_reported_as_a_parked_domain_is_still_treated_as_pre_existing(): void
    {
        Http::fake([
            '*DomainInfo/domains_data*' => Http::response(['status' => 1, 'data' => [
                'main_domain' => 'johndoe.example.com',
                'domains'     => [
                    ['domain' => 'parked.johndoe.example.com', 'domain_type' => 'parked', 'documentroot' => '/home/johndoe/public_html'],
                ],
            ]], 200),
        ]);
        $ctx = $this->context($this->server(), 'parked.johndoe.example.com');

        $result = $this->provisioner()->createDomain($ctx, 'parked.johndoe.example.com');

        $this->assertTrue($result->success);
        $this->assertSame('already_exists', $result->status);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'addsubdomain') || str_contains($request->url(), 'addaddondomain'));
    }
}
