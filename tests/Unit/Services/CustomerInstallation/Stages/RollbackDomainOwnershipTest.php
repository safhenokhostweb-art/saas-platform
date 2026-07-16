<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Domain\CpanelDomainProvisioner;
use App\Services\CustomerInstallation\Domain\DomainProvisionerFactory;
use App\Services\CustomerInstallation\Domain\VpsDomainProvisioner;
use App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use App\Services\CustomerInstallation\Stages\ProvisionDomainStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 15-D — Part 8 (Domain Ownership Safety) dedicated regression suite:
 * the installer must NEVER remove a domain the customer already had before
 * this pipeline ran. ProvisionDomainStageTest covers the stage's decision
 * logic against spy provisioners; this file exercises the REAL
 * CpanelDomainProvisioner (over Http::fake(), so no real UAPI call is
 * needed) across the full snapshot matrix this safety rule depends on:
 * every domain type (main/subdomain/addon) crossed with every
 * created_by_installer value the pipeline can actually produce.
 */
class RollbackDomainOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function context(string $domain = 'shop.johndoe.example.com'): InstallationContext
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
            'status'            => 'failed',
            'customer_company'  => 'Ownership Co',
            'customer_email'    => 'ownership@example.com',
            'customer_domain'   => $domain,
            'request_payload'   => [
                'company' => 'Ownership Co', 'owner_name' => 'Owner', 'email' => 'ownership@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => $domain, 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => true, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);
        $driver  = new CpanelDeploymentDriver($server, $vault);

        return new InstallationContext($installation, $request, $driver);
    }

    private function stage(): ProvisionDomainStage
    {
        return new ProvisionDomainStage(new DomainProvisionerFactory(
            new VpsDomainProvisioner(),
            new CpanelDomainProvisioner(new CpanelUapiClient(new CredentialVault())),
        ));
    }

    public function test_main_domain_is_never_removed_even_if_created_by_installer_were_ever_mistakenly_true(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);
        $ctx = $this->context('johndoe.example.com');

        // Defense-in-depth: main domains are never marked created_by_installer=true
        // by CpanelDomainProvisioner::createDomain() (see its 'main' branch),
        // but this proves the rollback path is ALSO safe if that invariant
        // were ever violated by a future bug — a 'main' type snapshot never
        // reaches a delete-capable code path either.
        $this->stage()->rollback($ctx, ['domain' => 'johndoe.example.com', 'type' => 'main', 'created_by_installer' => true]);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'delsubdomain') || str_contains($request->url(), 'deladdondomain'));
    }

    public function test_subdomain_created_by_installer_is_removed_on_rollback(): void
    {
        Http::fake(['*SubDomain/delsubdomain*' => Http::response(['status' => 1, 'data' => []], 200)]);
        $ctx = $this->context();

        $this->stage()->rollback($ctx, ['domain' => 'shop.johndoe.example.com', 'type' => 'subdomain', 'created_by_installer' => true]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/execute/SubDomain/delsubdomain'));
    }

    public function test_subdomain_not_created_by_installer_is_never_removed(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);
        $ctx = $this->context();

        $this->stage()->rollback($ctx, ['domain' => 'shop.johndoe.example.com', 'type' => 'subdomain', 'created_by_installer' => false]);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'delsubdomain'));
    }

    public function test_addon_domain_created_by_installer_is_removed_on_rollback(): void
    {
        Http::fake(['*AddonDomain/deladdondomain*' => Http::response(['status' => 1, 'data' => []], 200)]);
        $ctx = $this->context('othercompany.com');

        $this->stage()->rollback($ctx, [
            'domain' => 'othercompany.com', 'type' => 'addon', 'created_by_installer' => true,
            'meta' => ['subdomain_prefix' => 'othercompany-com'],
        ]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/execute/AddonDomain/deladdondomain')
            && $request['subdomain'] === 'othercompany-com');
    }

    public function test_addon_domain_not_created_by_installer_is_never_removed(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);
        $ctx = $this->context('othercompany.com');

        $this->stage()->rollback($ctx, ['domain' => 'othercompany.com', 'type' => 'addon', 'created_by_installer' => false]);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'deladdondomain'));
    }

    /**
     * A snapshot missing the created_by_installer key at all (e.g. a very
     * old, pre-15-D checkpoint, or any future bug that omits it) must
     * default to the SAFE outcome (do not remove) — never the destructive one.
     */
    public function test_snapshot_missing_created_by_installer_key_defaults_to_safe_no_removal(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);
        $ctx = $this->context();

        $this->stage()->rollback($ctx, ['domain' => 'shop.johndoe.example.com', 'type' => 'subdomain']);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'delsubdomain'));
    }
}
