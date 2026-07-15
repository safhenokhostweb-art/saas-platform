<?php

namespace Tests\Unit\CustomerInstallation\Provisioning;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver;
use App\Services\CustomerInstallation\Provisioning\CpanelDatabaseProvisioner;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CpanelDatabaseProvisionerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function context(CustomerRemoteServer $server): InstallationContext
    {
        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Cpanel Co',
            'customer_email'    => 'cp@example.com',
            'customer_domain'   => 'cp.example.com',
            'request_payload'   => [
                'company' => 'Cpanel Co', 'owner_name' => 'Owner', 'email' => 'cp@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'cp.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
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

    public function test_create_database_calls_correct_uapi_endpoint_and_auth(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);
        $server = $this->passwordServer();
        $ctx = $this->context($server);

        (new CpanelDatabaseProvisioner(new CpanelUapiClient(new CredentialVault())))
            ->createDatabase($ctx, 'johndoe_abc123');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://box106.parsvds.com:2083/execute/Mysql/create_database'
                && $request['name'] === 'johndoe_abc123'
                && $request->hasHeader('Authorization', 'cpanel johndoe:the-account-password');
        });
    }

    public function test_create_user_calls_correct_uapi_endpoint(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);
        $server = $this->passwordServer();
        $ctx = $this->context($server);

        (new CpanelDatabaseProvisioner(new CpanelUapiClient(new CredentialVault())))
            ->createUser($ctx, 'johndoe_xyz789', 'GeneratedPass1!');

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/execute/Mysql/create_user')
                && $request['name'] === 'johndoe_xyz789'
                && $request['password'] === 'GeneratedPass1!';
        });
    }

    public function test_grant_privileges_calls_set_privileges_on_user(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);
        $server = $this->passwordServer();
        $ctx = $this->context($server);

        (new CpanelDatabaseProvisioner(new CpanelUapiClient(new CredentialVault())))
            ->grantPrivileges($ctx, 'johndoe_abc123', 'johndoe_xyz789');

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/execute/Mysql/set_privileges_on_user')
                && $request['database'] === 'johndoe_abc123'
                && $request['user'] === 'johndoe_xyz789'
                && $request['privileges'] === 'ALL PRIVILEGES';
        });
    }

    public function test_api_token_is_preferred_over_password_for_authentication(): void
    {
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);
        $vault = new CredentialVault();
        $server = CustomerRemoteServer::create([
            'label' => 'cPanel', 'type' => 'cpanel', 'host' => 'box.example.test',
            'port' => 22, 'username' => 'johndoe', 'auth_method' => 'api_token',
            'password_encrypted'  => $vault->encrypt('should-not-be-used'),
            'api_token_encrypted' => $vault->encrypt('the-api-token'),
        ]);
        $ctx = $this->context($server);

        (new CpanelDatabaseProvisioner(new CpanelUapiClient($vault)))->createDatabase($ctx, 'johndoe_abc123');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'cpanel johndoe:the-api-token'));
    }

    public function test_uapi_level_failure_throws_with_the_reported_error(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 0,
            'errors' => ['Database "johndoe_abc123" already exists.'],
        ], 200)]);
        $server = $this->passwordServer();
        $ctx = $this->context($server);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already exists');

        (new CpanelDatabaseProvisioner(new CpanelUapiClient(new CredentialVault())))
            ->createDatabase($ctx, 'johndoe_abc123');
    }

    public function test_http_level_failure_throws(): void
    {
        Http::fake(['*' => Http::response('Internal Server Error', 500)]);
        $server = $this->passwordServer();
        $ctx = $this->context($server);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500');

        (new CpanelDatabaseProvisioner(new CpanelUapiClient(new CredentialVault())))
            ->createDatabase($ctx, 'johndoe_abc123');
    }

    public function test_drop_database_and_drop_user_never_throw_even_on_uapi_failure(): void
    {
        Http::fake(['*' => Http::response(['status' => 0, 'errors' => ['not found']], 200)]);
        $server = $this->passwordServer();
        $ctx = $this->context($server);

        $provisioner = new CpanelDatabaseProvisioner(new CpanelUapiClient(new CredentialVault()));
        // Must not throw — matches VpsDatabaseProvisioner's best-effort rollback.
        $provisioner->dropDatabase($ctx, 'johndoe_abc123');
        $provisioner->dropUser($ctx, 'johndoe_xyz789');

        Http::assertSentCount(2);
        $this->addToAssertionCount(1);
    }

    public function test_host_stored_with_scheme_and_port_is_normalized(): void
    {
        // Exact shape from the Installation #44 root-cause audit.
        Http::fake(['*' => Http::response(['status' => 1, 'data' => []], 200)]);
        $vault = new CredentialVault();
        $server = CustomerRemoteServer::create([
            'label' => 'cPanel', 'type' => 'cpanel', 'host' => 'https://box106.parsvds.com:2083',
            'port' => 22, 'username' => 'johndoe', 'auth_method' => 'password',
            'password_encrypted' => $vault->encrypt('pw'),
        ]);
        $ctx = $this->context($server);

        (new CpanelDatabaseProvisioner(new CpanelUapiClient($vault)))->createDatabase($ctx, 'johndoe_abc123');

        Http::assertSent(fn ($request) => $request->url() === 'https://box106.parsvds.com:2083/execute/Mysql/create_database');
    }
}
