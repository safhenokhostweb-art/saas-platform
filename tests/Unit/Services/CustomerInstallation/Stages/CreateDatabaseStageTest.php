<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Provisioning\CpanelDatabaseProvisioner;
use App\Services\CustomerInstallation\Provisioning\DatabaseProvisionerFactory;
use App\Services\CustomerInstallation\Provisioning\VpsDatabaseProvisioner;
use App\Services\CustomerInstallation\Stages\CreateDatabaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 14-F2 — proves CreateDatabaseStage is hosting-agnostic: it is
 * exercised here against a spy provisioner that has nothing to do with
 * mysql or cPanel, and the stage still succeeds and produces the same
 * output/rollback-snapshot shape as before — because it only ever talks to
 * DatabaseProvisionerInterface, never to a transport directly.
 */
class CreateDatabaseStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function spyProvisioner(array &$calls): VpsDatabaseProvisioner
    {
        return new class($calls) extends VpsDatabaseProvisioner {
            public function __construct(private array &$calls) {}
            public function createDatabase(InstallationContext $ctx, string $dbName): void { $this->calls[] = ['createDatabase', $dbName]; }
            public function createUser(InstallationContext $ctx, string $dbUser, string $dbPassword): void { $this->calls[] = ['createUser', $dbUser, $dbPassword]; }
            public function grantPrivileges(InstallationContext $ctx, string $dbName, string $dbUser): void { $this->calls[] = ['grantPrivileges', $dbName, $dbUser]; }
            public function dropDatabase(InstallationContext $ctx, string $dbName): void { $this->calls[] = ['dropDatabase', $dbName]; }
            public function dropUser(InstallationContext $ctx, string $dbUser): void { $this->calls[] = ['dropUser', $dbUser]; }
        };
    }

    private function context(FakeRemoteDriver $driver): InstallationContext
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
            'customer_company'  => 'Stage Test Co',
            'customer_email'    => 'stage@example.com',
            'customer_domain'   => 'stage.example.com',
            'request_payload'   => [
                'company' => 'Stage Test Co', 'owner_name' => 'Owner', 'email' => 'stage@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'stage.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);

        return new InstallationContext($installation, $request, $driver);
    }

    public function test_execute_calls_provisioner_methods_in_order_and_nothing_else(): void
    {
        $calls = [];
        $vpsSpy = $this->spyProvisioner($calls);
        $cpanelDummy = $this->createStub(CpanelDatabaseProvisioner::class);

        $stage = new CreateDatabaseStage(new DatabaseProvisionerFactory($vpsSpy, $cpanelDummy));
        $ctx = $this->context(new FakeRemoteDriver());

        $result = $stage->execute($ctx);

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertSame('createDatabase', $calls[0][0]);
        $this->assertSame('createUser', $calls[1][0]);
        $this->assertSame('grantPrivileges', $calls[2][0]);

        // The same names flow through: createDatabase's dbName === grantPrivileges' dbName,
        // createUser's dbUser === grantPrivileges' dbUser.
        $this->assertSame($calls[0][1], $calls[2][1]);
        $this->assertSame($calls[1][1], $calls[2][2]);

        $this->assertSame($result->output['db_name'], $calls[0][1]);
        $this->assertSame($result->output['db_user'], $calls[1][1]);
    }

    public function test_rollback_calls_drop_database_then_drop_user_on_the_resolved_provisioner(): void
    {
        $calls = [];
        $vpsSpy = $this->spyProvisioner($calls);
        $cpanelDummy = $this->createStub(CpanelDatabaseProvisioner::class);

        $stage = new CreateDatabaseStage(new DatabaseProvisionerFactory($vpsSpy, $cpanelDummy));
        $ctx = $this->context(new FakeRemoteDriver());

        $stage->rollback($ctx, ['db_name' => 'cust_x_1', 'db_user' => 'u_y']);

        $this->assertSame(['dropDatabase', 'cust_x_1'], $calls[0]);
        $this->assertSame(['dropUser', 'u_y'], $calls[1]);
    }

    public function test_required_capabilities_declares_database_creation(): void
    {
        $stage = new CreateDatabaseStage(new DatabaseProvisionerFactory(
            new VpsDatabaseProvisioner(),
            $this->createStub(CpanelDatabaseProvisioner::class),
        ));

        $this->assertSame(['supportsDatabaseCreation'], $stage->requiredCapabilities());
        $this->assertTrue($stage->isCapabilityRequiredToProceed());
    }

    public function test_reinstall_mode_fresh_resets_existing_database_and_user_belonging_to_same_deleted_site(): void
    {
        $calls = [];
        $vpsSpy = new class($calls) extends VpsDatabaseProvisioner {
            public function __construct(private array &$calls) {}
            public function databaseExists(InstallationContext $ctx, string $dbName): ?bool { return true; }
            public function databaseUserExists(InstallationContext $ctx, string $dbUser): ?bool { return true; }
            public function dropDatabase(InstallationContext $ctx, string $dbName): void { $this->calls[] = ['dropDatabase', $dbName]; }
            public function dropUser(InstallationContext $ctx, string $dbUser): void { $this->calls[] = ['dropUser', $dbUser]; }
            public function createDatabase(InstallationContext $ctx, string $dbName): void { $this->calls[] = ['createDatabase', $dbName]; }
            public function createUser(InstallationContext $ctx, string $dbUser, string $dbPassword): void { $this->calls[] = ['createUser', $dbUser, $dbPassword]; }
            public function grantPrivileges(InstallationContext $ctx, string $dbName, string $dbUser): void { $this->calls[] = ['grantPrivileges', $dbName, $dbUser]; }
        };

        $stage = new CreateDatabaseStage(new DatabaseProvisionerFactory(
            $vpsSpy,
            $this->createStub(CpanelDatabaseProvisioner::class),
        ));

        // Create a customer first to satisfy foreign key
        $customer = \App\Models\Customer::create([
            'name' => 'Test Customer',
            'mobile' => '09123456789',
        ]);

        // Create an existing deleted/inactive site with same domain
        \App\Models\Site::create([
            'customer_id' => $customer->id,
            'domain' => 'stage.example.com',
            'title' => 'Old Site',
            'is_active' => false,
            'lifecycle_status' => \App\Models\Site::LIFECYCLE_DELETED,
            'db_name' => 'cust_old_db',
            'db_user' => 'u_old_user',
        ]);

        $ctx = $this->context(new FakeRemoteDriver());
        $result = $stage->execute($ctx);

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertSame('dropDatabase', $calls[0][0]);
        $this->assertSame('dropUser', $calls[1][0]);
        $this->assertSame('createDatabase', $calls[2][0]);
        $this->assertSame('createUser', $calls[3][0]);
        $this->assertSame('grantPrivileges', $calls[4][0]);
    }

    public function test_existing_database_fails_when_belonging_to_another_active_site(): void
    {
        $calls = [];
        $vpsSpy = new class($calls) extends VpsDatabaseProvisioner {
            public function __construct(private array &$calls) {}
            public function databaseExists(InstallationContext $ctx, string $dbName): ?bool { return true; }
            public function databaseUserExists(InstallationContext $ctx, string $dbUser): ?bool { return false; }
        };

        $stage = new CreateDatabaseStage(new DatabaseProvisionerFactory(
            $vpsSpy,
            $this->createStub(CpanelDatabaseProvisioner::class),
        ));

        $customer2 = \App\Models\Customer::create([
            'name' => 'Other Customer',
            'mobile' => '09120000000',
        ]);

        // Another active site has this domain or no matching domain
        \App\Models\Site::create([
            'customer_id' => $customer2->id,
            'domain' => 'other.example.com',
            'title' => 'Other Site',
            'is_active' => true,
            'lifecycle_status' => \App\Models\Site::LIFECYCLE_ACTIVE,
            'db_name' => 'cust_other_db',
            'db_user' => 'u_other_user',
        ]);

        $ctx = $this->context(new FakeRemoteDriver());
        $result = $stage->execute($ctx);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('از قبل روی این سرور وجود دارد', $result->error);
    }
}
