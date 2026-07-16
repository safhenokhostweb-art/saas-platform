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
 * Phase 15-D — CpanelDomainProvisioner exercises the UAPI DomainInfo/
 * SubDomain/AddonDomain module calls over Http::fake(), the same pattern
 * CpanelDatabaseProvisionerTest uses for the Mysql module. See the class
 * docblock: the exact UAPI function/parameter shapes are this
 * implementation's best-documented understanding, not verified against a
 * live cPanel account — these tests verify THIS CLASS'S behavior (correct
 * request shape, idempotency, graceful degradation), not that cPanel's real
 * API matches these names.
 */
class CpanelDomainProvisionerTest extends TestCase
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
            'customer_company'  => 'Domain Co',
            'customer_email'    => 'domain@example.com',
            'customer_domain'   => $domain,
            'request_payload'   => [
                'company' => 'Domain Co', 'owner_name' => 'Owner', 'email' => 'domain@example.com',
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

    public function test_main_domain_is_detected_as_already_existing_without_any_creation_call(): void
    {
        Http::fake([
            '*DomainInfo/domains_data*' => Http::response(['status' => 1, 'data' => [
                'main_domain' => 'johndoe.example.com',
                'domains'     => [],
            ]], 200),
        ]);
        $ctx = $this->context($this->server(), 'johndoe.example.com');

        $result = $this->provisioner()->createDomain($ctx, 'johndoe.example.com');

        $this->assertTrue($result->success);
        $this->assertSame('already_exists', $result->status);
        $this->assertSame('main', $result->type);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'SubDomain') || str_contains($request->url(), 'AddonDomain'));
    }

    public function test_subdomain_is_created_via_uapi_when_not_already_present(): void
    {
        Http::fake([
            '*DomainInfo/domains_data*' => Http::response(['status' => 1, 'data' => [
                'main_domain' => 'johndoe.example.com',
                'domains'     => [],
            ]], 200),
            '*SubDomain/addsubdomain*' => Http::response(['status' => 1, 'data' => []], 200),
        ]);
        $ctx = $this->context($this->server(), 'shop.johndoe.example.com');

        $result = $this->provisioner()->createDomain($ctx, 'shop.johndoe.example.com');

        $this->assertTrue($result->success);
        $this->assertSame('created', $result->status);
        $this->assertSame('subdomain', $result->type);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/execute/SubDomain/addsubdomain')
                && $request['domain'] === 'shop'
                && $request['rootdomain'] === 'johndoe.example.com'
                && $request->hasHeader('Authorization', 'cpanel johndoe:the-account-password');
        });
    }

    public function test_addon_domain_is_created_via_uapi_when_not_a_subdomain_of_main(): void
    {
        Http::fake([
            '*DomainInfo/domains_data*' => Http::response(['status' => 1, 'data' => [
                'main_domain' => 'johndoe.example.com',
                'domains'     => [],
            ]], 200),
            '*AddonDomain/addaddondomain*' => Http::response(['status' => 1, 'data' => []], 200),
        ]);
        $ctx = $this->context($this->server(), 'othercompany.com');

        $result = $this->provisioner()->createDomain($ctx, 'othercompany.com');

        $this->assertTrue($result->success);
        $this->assertSame('created', $result->status);
        $this->assertSame('addon', $result->type);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/execute/AddonDomain/addaddondomain')
                && $request['newdomain'] === 'othercompany.com'
                && !empty($request['subdomain'])
                && !empty($request['dir']);
        });
    }

    public function test_existing_subdomain_is_idempotent_not_recreated(): void
    {
        Http::fake([
            '*DomainInfo/domains_data*' => Http::response(['status' => 1, 'data' => [
                'main_domain' => 'johndoe.example.com',
                'domains'     => [
                    'shop.johndoe.example.com' => ['domain_type' => 'sub', 'documentroot' => '/home/johndoe/public_html/shop'],
                ],
            ]], 200),
        ]);
        $ctx = $this->context($this->server(), 'shop.johndoe.example.com');

        $result = $this->provisioner()->createDomain($ctx, 'shop.johndoe.example.com');

        $this->assertTrue($result->success);
        $this->assertSame('already_exists', $result->status);
        $this->assertSame('/home/johndoe/public_html/shop', $result->documentRoot);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'addsubdomain'));
    }

    public function test_create_domain_fails_when_domain_info_query_fails(): void
    {
        Http::fake(['*' => Http::response('Internal Server Error', 500)]);
        $ctx = $this->context($this->server(), 'shop.johndoe.example.com');

        $result = $this->provisioner()->createDomain($ctx, 'shop.johndoe.example.com');

        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
    }

    public function test_create_domain_fails_when_addsubdomain_api_call_fails(): void
    {
        Http::fake([
            '*DomainInfo/domains_data*' => Http::response(['status' => 1, 'data' => [
                'main_domain' => 'johndoe.example.com',
                'domains'     => [],
            ]], 200),
            '*SubDomain/addsubdomain*' => Http::response(['status' => 0, 'errors' => ['subdomain limit reached']], 200),
        ]);
        $ctx = $this->context($this->server(), 'shop.johndoe.example.com');

        $result = $this->provisioner()->createDomain($ctx, 'shop.johndoe.example.com');

        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
        $this->assertStringContainsString('subdomain limit reached', $result->message);
    }

    public function test_remove_domain_calls_delsubdomain_for_subdomain_type(): void
    {
        Http::fake(['*SubDomain/delsubdomain*' => Http::response(['status' => 1, 'data' => []], 200)]);
        $ctx = $this->context($this->server(), 'shop.johndoe.example.com');

        $this->provisioner()->removeDomain($ctx, 'shop.johndoe.example.com', ['type' => 'subdomain']);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/execute/SubDomain/delsubdomain')
            && $request['domain'] === 'shop.johndoe.example.com');
    }

    public function test_remove_domain_calls_deladdondomain_for_addon_type(): void
    {
        Http::fake(['*AddonDomain/deladdondomain*' => Http::response(['status' => 1, 'data' => []], 200)]);
        $ctx = $this->context($this->server(), 'othercompany.com');

        $this->provisioner()->removeDomain($ctx, 'othercompany.com', [
            'type' => 'addon',
            'meta' => ['subdomain_prefix' => 'othercompany-com'],
        ]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/execute/AddonDomain/deladdondomain')
            && $request['domain'] === 'othercompany.com'
            && $request['subdomain'] === 'othercompany-com');
    }

    public function test_remove_domain_never_throws_on_uapi_failure(): void
    {
        Http::fake(['*' => Http::response(['status' => 0, 'errors' => ['not found']], 200)]);
        $ctx = $this->context($this->server(), 'shop.johndoe.example.com');

        $this->provisioner()->removeDomain($ctx, 'shop.johndoe.example.com', ['type' => 'subdomain']);

        $this->addToAssertionCount(1); // reaching here means it didn't throw
    }

    public function test_domain_exists_returns_null_when_uapi_is_unreachable(): void
    {
        Http::fake(['*' => Http::response('Gateway Timeout', 504)]);
        $ctx = $this->context($this->server(), 'shop.johndoe.example.com');

        $exists = $this->provisioner()->domainExists($ctx, 'shop.johndoe.example.com');

        $this->assertNull($exists);
    }

    public function test_no_whm_or_root_credential_is_ever_used(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => ['main_domain' => 'johndoe.example.com', 'domains' => []]], 200)]);
        $ctx = $this->context($this->server(), 'johndoe.example.com');

        $this->provisioner()->createDomain($ctx, 'johndoe.example.com');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'cpanel johndoe:the-account-password'));
    }
}
