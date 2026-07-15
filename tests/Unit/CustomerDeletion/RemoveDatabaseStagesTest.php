<?php

namespace Tests\Unit\CustomerDeletion;

use App\Models\CustomerDeletion;
use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationLog;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Stages\RemoveDatabaseStage;
use App\Services\CustomerDeletion\Stages\RemoveDatabaseUserStage;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Dto\DriverCapabilities;
use App\Services\CustomerInstallation\Enums\DriverType;
use App\Services\CustomerInstallation\Provisioning\CpanelDatabaseProvisioner;
use App\Services\CustomerInstallation\Provisioning\CpanelUapiClient;
use App\Services\CustomerInstallation\Provisioning\DatabaseProvisionerFactory;
use App\Services\CustomerInstallation\Provisioning\VpsDatabaseProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 14-G3 — RemoveDatabaseStage / RemoveDatabaseUserStage no longer run
 * `mysql -u root` themselves: they resolve the hosting-appropriate
 * DatabaseProvisionerInterface via DatabaseProvisionerFactory (VPS: same
 * root-mysql DROP as before; cPanel: UAPI Mysql::delete_database /
 * delete_user — the same calls the install rollback path has used since
 * 14-F2) and verify the outcome with the provisioner's read-only probe.
 */
class RemoveDatabaseStagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;

        parent::setUp();
    }

    private function makeDeletion(string $serverType = 'fake'): CustomerDeletion
    {
        $server = CustomerRemoteServer::create([
            'label' => 'S', 'type' => $serverType, 'host' => 'box.example.test',
            'port' => 22, 'username' => 'johndoe', 'auth_method' => 'password',
            'password_encrypted' => (new CredentialVault())->encrypt('the-password'),
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'complete',
            'customer_company'  => 'Db Del Co',
            'customer_email'    => 'dbdel@example.com',
            'customer_domain'   => 'dbdel.example.com',
            'request_payload'   => [
                'company' => 'Db Del Co', 'owner_name' => 'Owner', 'email' => 'dbdel@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'dbdel.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        // What the install actually recorded — the deletion's source of truth.
        CustomerInstallationLog::create([
            'installation_id' => $installation->id,
            'stage'           => 'CREATE_DATABASE',
            'event'           => 'completed',
            'message'         => 'done',
            'output'          => ['db_name' => 'cust_db_1', 'db_user' => 'u_deadbeef'],
            'created_at'      => now(),
        ]);

        return CustomerDeletion::create([
            'installation_id' => $installation->id,
            'initiated_by'    => 1,
            'policy'          => 'never_delete_backup',
            'status'          => 'running',
        ]);
    }

    private function factory(): DatabaseProvisionerFactory
    {
        return new DatabaseProvisionerFactory(
            new VpsDatabaseProvisioner(),
            new CpanelDatabaseProvisioner(new CpanelUapiClient(new CredentialVault())),
        );
    }

    private function context(CustomerDeletion $deletion, FakeRemoteDriver $driver): DeleteContext
    {
        return DeleteContext::fromDeletion($deletion, $driver);
    }

    // ─── VPS: behavior unchanged ──────────────────────────────────────────────

    public function test_vps_drop_database_issues_the_same_root_mysql_drop(): void
    {
        $driver = new FakeRemoteDriver();
        $ctx = $this->context($this->makeDeletion(), $driver);

        $result = (new RemoveDatabaseStage($this->factory()))->execute($ctx);

        $this->assertTrue($result->success, $result->error ?? '');
        $log = implode("\n", $driver->getCommandLog());
        $this->assertStringContainsString('mysql -u root', $log);
        $this->assertStringContainsString('DROP DATABASE IF EXISTS `cust_db_1`;', $log);
    }

    public function test_vps_fails_when_database_survives_the_drop(): void
    {
        $driver = new FakeRemoteDriver();
        // Existence probe reports the database still present (wc -l > 1).
        $driver->addResponse('SHOW DATABASES', 0, '2');
        $ctx = $this->context($this->makeDeletion(), $driver);

        $result = (new RemoveDatabaseStage($this->factory()))->execute($ctx);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('still exists', $result->error);
    }

    public function test_vps_drop_user_issues_the_same_root_mysql_drop(): void
    {
        $driver = new FakeRemoteDriver();
        $ctx = $this->context($this->makeDeletion(), $driver);

        $result = (new RemoveDatabaseUserStage($this->factory()))->execute($ctx);

        $this->assertTrue($result->success, $result->error ?? '');
        $log = implode("\n", $driver->getCommandLog());
        $this->assertStringContainsString("DROP USER IF EXISTS 'u_deadbeef'@'localhost';", $log);
    }

    // ─── cPanel: UAPI, never root mysql ───────────────────────────────────────

    public function test_cpanel_drop_database_calls_uapi_and_never_root_mysql(): void
    {
        Http::fake([
            '*Mysql/delete_database' => Http::response(['status' => 1, 'data' => []], 200),
            '*Mysql/list_databases'  => Http::response(['status' => 1, 'data' => []], 200),
        ]);

        $driver = new FakeRemoteDriver(driverType: DriverType::CPANEL);
        $ctx = $this->context($this->makeDeletion('cpanel'), $driver);

        $result = (new RemoveDatabaseStage($this->factory()))->execute($ctx);

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertSame([], $driver->getCommandLog(), 'cPanel deletion must not run any shell command for the DB drop');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/execute/Mysql/delete_database')
            && $request['name'] === 'cust_db_1');
    }

    public function test_cpanel_drop_user_calls_uapi_and_never_root_mysql(): void
    {
        Http::fake([
            '*Mysql/delete_user' => Http::response(['status' => 1, 'data' => []], 200),
            '*Mysql/list_users'  => Http::response(['status' => 1, 'data' => []], 200),
        ]);

        $driver = new FakeRemoteDriver(driverType: DriverType::CPANEL);
        $ctx = $this->context($this->makeDeletion('cpanel'), $driver);

        $result = (new RemoveDatabaseUserStage($this->factory()))->execute($ctx);

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertSame([], $driver->getCommandLog());
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/execute/Mysql/delete_user')
            && $request['name'] === 'u_deadbeef');
    }

    public function test_cpanel_fails_when_database_survives_according_to_uapi(): void
    {
        Http::fake([
            '*Mysql/delete_database' => Http::response(['status' => 1, 'data' => []], 200),
            '*Mysql/list_databases'  => Http::response(['status' => 1, 'data' => [['database' => 'cust_db_1']]], 200),
        ]);

        $driver = new FakeRemoteDriver(driverType: DriverType::CPANEL);
        $ctx = $this->context($this->makeDeletion('cpanel'), $driver);

        $result = (new RemoveDatabaseStage($this->factory()))->execute($ctx);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('still exists', $result->error);
    }

    // ─── Capability gate ──────────────────────────────────────────────────────

    public function test_driver_without_database_deletion_capability_skips_safely(): void
    {
        $driver = new FakeRemoteDriver(DriverCapabilities::forSftp());
        $ctx = $this->context($this->makeDeletion(), $driver);

        $dbResult   = (new RemoveDatabaseStage($this->factory()))->execute($ctx);
        $userResult = (new RemoveDatabaseUserStage($this->factory()))->execute($ctx);

        $this->assertTrue($dbResult->success);
        $this->assertTrue($dbResult->skipped);
        $this->assertTrue($userResult->success);
        $this->assertTrue($userResult->skipped);
        $this->assertSame([], $driver->getCommandLog(), 'no command may run when the capability is missing');
    }
}
