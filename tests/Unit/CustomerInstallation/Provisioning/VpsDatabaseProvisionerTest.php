<?php

namespace Tests\Unit\CustomerInstallation\Provisioning;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Provisioning\VpsDatabaseProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 14-F2 — VpsDatabaseProvisioner was extracted verbatim from the
 * pre-14-F2 CreateDatabaseStage. These tests pin the exact SQL commands and
 * retry/error behavior so the extraction is provably a no-op for VPS/SSH.
 */
class VpsDatabaseProvisionerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function context(FakeRemoteDriver $driver): InstallationContext
    {
        $server = CustomerRemoteServer::create([
            'label' => 'VPS', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Vps Co',
            'customer_email'    => 'vps@example.com',
            'customer_domain'   => 'vps.example.com',
            'request_payload'   => [
                'company' => 'Vps Co', 'owner_name' => 'Owner', 'email' => 'vps@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'vps.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);

        return new InstallationContext($installation, $request, $driver);
    }

    public function test_create_database_sends_expected_sql(): void
    {
        $driver = new FakeRemoteDriver();
        $ctx = $this->context($driver);

        (new VpsDatabaseProvisioner())->createDatabase($ctx, 'cust_abc123_1');

        $log = $driver->getCommandLog();
        $this->assertCount(1, $log);
        $this->assertStringContainsString('mysql -u root 2>/dev/null -e', $log[0]);
        $this->assertStringContainsString(
            'CREATE DATABASE IF NOT EXISTS `cust_abc123_1` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;',
            $log[0]
        );
    }

    public function test_create_user_sends_expected_sql(): void
    {
        $driver = new FakeRemoteDriver();
        $ctx = $this->context($driver);

        // Note: deliberately no "!" in this password — PHP's escapeshellarg()
        // on a Windows host (this dev/test environment) mangles "!" because
        // of cmd.exe delayed-expansion quoting; that's a platform artifact
        // of escapeshellarg() itself, identical before and after this
        // extraction, not something VpsDatabaseProvisioner changed.
        (new VpsDatabaseProvisioner())->createUser($ctx, 'u_deadbeef', 'S3cretPass1');

        $log = $driver->getCommandLog();
        $this->assertStringContainsString(
            "CREATE USER IF NOT EXISTS 'u_deadbeef'@'localhost' IDENTIFIED BY 'S3cretPass1';",
            $log[0]
        );
    }

    public function test_grant_privileges_sends_grant_then_flush(): void
    {
        $driver = new FakeRemoteDriver();
        $ctx = $this->context($driver);

        (new VpsDatabaseProvisioner())->grantPrivileges($ctx, 'cust_abc123_1', 'u_deadbeef');

        $log = $driver->getCommandLog();
        $this->assertCount(2, $log);
        $this->assertStringContainsString(
            "GRANT ALL PRIVILEGES ON `cust_abc123_1`.* TO 'u_deadbeef'@'localhost';",
            $log[0]
        );
        $this->assertStringContainsString('FLUSH PRIVILEGES;', $log[1]);
    }

    public function test_retries_without_mysql_pwd_env_when_first_attempt_fails(): void
    {
        $driver = new FakeRemoteDriver();
        // First attempt (with MYSQL_PWD env trick) fails; the fallback
        // attempt has no registered response, so it defaults to success.
        $driver->addResponse('2>/dev/null -e', 1, 'access denied via socket');
        $ctx = $this->context($driver);

        (new VpsDatabaseProvisioner())->createDatabase($ctx, 'cust_abc123_1');

        $log = $driver->getCommandLog();
        $this->assertCount(2, $log, 'Expected exactly one retry attempt');
        $this->assertStringContainsString('2>/dev/null -e', $log[0]);
        $this->assertStringNotContainsString('2>/dev/null', $log[1]);
    }

    public function test_throws_with_clear_message_when_both_attempts_fail(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('mysql -u root', 1, 'access denied');
        $ctx = $this->context($driver);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MySQL command failed');

        (new VpsDatabaseProvisioner())->createDatabase($ctx, 'cust_abc123_1');
    }

    /**
     * Phase 14-G1 — createUser()'s SQL embeds the freshly-generated tenant DB
     * password via IDENTIFIED BY '<password>'. Before this fix, a failed
     * CREATE_DATABASE stage on VPS captured that raw SQL verbatim in the
     * RuntimeException message, which InstallationStateManager then persisted
     * unredacted into customer_installation_logs.message and
     * customer_installations.failure_reason — both rendered on the admin
     * installation detail page. The exception message must describe the
     * statement kind (a secret-free label) rather than the SQL itself.
     */
    public function test_create_user_failure_does_not_leak_the_password_in_the_exception_message(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('mysql -u root', 1, 'access denied');
        $ctx = $this->context($driver);

        try {
            (new VpsDatabaseProvisioner())->createUser($ctx, 'u_deadbeef', 'S3cretPass1');
            $this->fail('Expected a RuntimeException to be thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('CREATE USER', $e->getMessage());
            $this->assertStringNotContainsString('S3cretPass1', $e->getMessage());
            $this->assertStringNotContainsString('IDENTIFIED BY', $e->getMessage());
        }
    }

    public function test_drop_database_and_drop_user_send_expected_sql_and_never_throw(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('mysql -u root', 1, 'anything, even a failure');
        $ctx = $this->context($driver);

        $provisioner = new VpsDatabaseProvisioner();
        // Must not throw even though the fake driver is configured to fail —
        // matches the original rollback() which never checked failed().
        $provisioner->dropDatabase($ctx, 'cust_abc123_1');
        $provisioner->dropUser($ctx, 'u_deadbeef');

        $log = $driver->getCommandLog();
        $this->assertStringContainsString('DROP DATABASE IF EXISTS `cust_abc123_1`;', $log[0]);
        $this->assertStringContainsString("DROP USER IF EXISTS 'u_deadbeef'@'localhost';", $log[1]);
    }
}
