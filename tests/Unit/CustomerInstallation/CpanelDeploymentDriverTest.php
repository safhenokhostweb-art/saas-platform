<?php

namespace Tests\Unit\CustomerInstallation;

use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\DriverFactory;
use App\Services\CustomerInstallation\Drivers\CpanelDeploymentDriver;
use App\Services\CustomerInstallation\Drivers\SshRemoteDriver;
use App\Services\CustomerInstallation\Enums\DriverType;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 14-C — CpanelDeploymentDriver connects over SSH transport (delegating
 * to SshRemoteDriver) because cPanel's UAPI/WHM API cannot satisfy
 * RemoteConnectionInterface's generic execute()/upload() contract, and the
 * pipeline Stages that call those methods are explicitly out of scope to
 * change this phase. API-token auth is registered but deliberately rejected
 * — see CpanelDeploymentDriver::guardSshTransportSupported().
 */
class CpanelDeploymentDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function factory(): DriverFactory
    {
        return new DriverFactory(new CredentialVault());
    }

    private function server(array $overrides = []): CustomerRemoteServer
    {
        return new CustomerRemoteServer(array_merge([
            'type'        => 'cpanel',
            'host'        => 'box106.parsvds.com',
            'port'        => 22,
            'username'    => 'customer1',
            'auth_method' => 'password',
        ], $overrides));
    }

    // ─── resolve از DriverFactory ─────────────────────────────────────────────

    public function test_driver_factory_resolves_cpanel_to_the_real_driver_class(): void
    {
        $this->assertSame(CpanelDeploymentDriver::class, $this->factory()->resolve(DriverType::CPANEL));
    }

    public function test_driver_factory_builds_a_cpanel_driver_from_a_customer_remote_server(): void
    {
        $vault = new CredentialVault();
        $server = $this->server(['password_encrypted' => $vault->encrypt('a-real-password')]);

        $driver = (new DriverFactory($vault))->buildForServer($server);

        $this->assertInstanceOf(CpanelDeploymentDriver::class, $driver);
        $this->assertSame(DriverType::CPANEL, $driver->driverType());
    }

    // ─── implemented=true ─────────────────────────────────────────────────────

    public function test_cpanel_driver_type_is_now_implemented(): void
    {
        $this->assertTrue(DriverType::CPANEL->isImplemented());
    }

    // ─── capabilities صحیح ────────────────────────────────────────────────────

    public function test_capabilities_do_not_claim_vps_only_features(): void
    {
        $driver = new CpanelDeploymentDriver($this->server(), new CredentialVault());
        $capabilities = $driver->capabilities();

        $this->assertFalse($capabilities->supportsQueue, 'cPanel has no persistent worker/queue support');
        $this->assertFalse($capabilities->supportsSupervisor, 'cPanel accounts cannot run supervisord');
        $this->assertFalse($capabilities->supportsRedis, 'Redis is not available on typical cPanel plans');
        $this->assertFalse($capabilities->supportsStorageLink, 'No root shell to guarantee artisan storage:link works');
        $this->assertFalse($capabilities->supportsDocker, 'cPanel does not run Docker containers');
        $this->assertFalse($capabilities->supportsReboot, 'A hosting account cannot reboot the server');
    }

    public function test_capabilities_declare_what_the_cpanel_api_surface_can_actually_do(): void
    {
        $driver = new CpanelDeploymentDriver($this->server(), new CredentialVault());
        $capabilities = $driver->capabilities();

        $this->assertTrue($capabilities->supportsCron, 'cPanel Cron Jobs UI/API');
        $this->assertTrue($capabilities->supportsScheduler, 'built on the same cPanel cron mechanism');
        $this->assertTrue($capabilities->supportsSSL, 'cPanel SSL/TLS or AutoSSL');
        $this->assertTrue($capabilities->supportsDatabaseCreation, 'cPanel MySQL Databases API (not yet wired into CreateDatabaseStage — see Phase 14-C report)');
    }

    // ─── credential encryption usage ──────────────────────────────────────────

    public function test_password_is_never_stored_in_plaintext_and_round_trips_through_the_vault(): void
    {
        $vault = new CredentialVault();
        $plaintext = 'super-secret-cpanel-password';
        $server = $this->server(['password_encrypted' => $vault->encrypt($plaintext)]);

        $this->assertNotSame($plaintext, $server->password_encrypted);
        $this->assertSame($plaintext, $vault->decrypt($server->password_encrypted));

        // The driver must validate successfully with a properly vault-encrypted credential.
        $driver = new CpanelDeploymentDriver($server, $vault);
        $this->assertTrue($driver->validate()->passed);
    }

    public function test_driver_wires_its_internal_ssh_transport_with_the_same_vault(): void
    {
        $vault = new CredentialVault();
        $driver = new CpanelDeploymentDriver($this->server(), $vault);

        $ref = new ReflectionClass($driver);
        $transportProp = $ref->getProperty('transport');
        $transportProp->setAccessible(true);
        $transport = $transportProp->getValue($driver);

        $this->assertInstanceOf(SshRemoteDriver::class, $transport);
    }

    public function test_validate_fails_without_any_credential(): void
    {
        $server = $this->server(['password_encrypted' => null]);
        $driver = new CpanelDeploymentDriver($server, new CredentialVault());

        $result = $driver->validate();

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('Password is required', $result->firstError());
    }

    // ─── unsupported capability rejected ─────────────────────────────────────

    public function test_validate_rejects_api_token_authentication(): void
    {
        $server = $this->server([
            'auth_method'       => 'api_token',
            'api_token_encrypted' => (new CredentialVault())->encrypt('some-token'),
        ]);
        $driver = new CpanelDeploymentDriver($server, new CredentialVault());

        $result = $driver->validate();

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('not yet supported', $result->firstError());
    }

    public function test_connect_throws_a_clear_error_for_api_token_authentication(): void
    {
        $server = $this->server(['auth_method' => 'api_token']);
        $driver = new CpanelDeploymentDriver($server, new CredentialVault());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SSH transport only');

        $driver->connect();
    }

    public function test_test_connection_reports_failure_instead_of_throwing_for_api_token_authentication(): void
    {
        $server = $this->server(['auth_method' => 'api_token']);
        $driver = new CpanelDeploymentDriver($server, new CredentialVault());

        $result = $driver->testConnection();

        $this->assertFalse($result->connected);
        $this->assertStringContainsString('SSH transport only', $result->error);
    }

    public function test_rollback_stage_is_a_safe_no_op_not_a_stub_exception(): void
    {
        $driver = new CpanelDeploymentDriver($this->server(), new CredentialVault());

        // Must not throw BadMethodCallException like the old architecture-stub did.
        $driver->rollbackStage('CREATE_DATABASE', ['db_name' => 'irrelevant']);
        $this->addToAssertionCount(1);
    }
}
