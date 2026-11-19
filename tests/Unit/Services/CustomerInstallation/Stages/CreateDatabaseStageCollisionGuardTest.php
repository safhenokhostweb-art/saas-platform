<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Provisioning\CpanelDatabaseProvisioner;
use App\Services\CustomerInstallation\Provisioning\DatabaseProvisionerFactory;
use App\Services\CustomerInstallation\Provisioning\VpsDatabaseProvisioner;
use App\Services\CustomerInstallation\Stages\CreateDatabaseStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 safety (2026-11-19): reviewed/custom database names must never adopt,
 * re-password or (on rollback) drop a database or user that already exists
 * on the server — it may belong to another tenant.
 */
class CreateDatabaseStageCollisionGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    public function test_existing_database_is_refused_before_any_create_or_grant(): void
    {
        $calls = [];
        $stage = $this->stage($calls, dbExists: true, userExists: false);

        $result = $stage->execute($this->context(['db_name' => 'cust_taken_db', 'db_user' => 'u_new']));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('cust_taken_db', (string) $result->error);
        $this->assertSame([], $this->mutations($calls), 'No CREATE/ALTER/GRANT may run against an existing database.');
    }

    public function test_existing_user_is_refused_so_its_password_is_never_reset(): void
    {
        $calls = [];
        $stage = $this->stage($calls, dbExists: false, userExists: true);

        $result = $stage->execute($this->context(['db_name' => 'cust_new_db', 'db_user' => 'u_taken']));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('u_taken', (string) $result->error);
        $this->assertSame([], $this->mutations($calls));
    }

    public function test_custom_names_fail_closed_when_existence_cannot_be_checked(): void
    {
        $calls = [];
        $stage = $this->stage($calls, dbExists: null, userExists: null);

        $result = $stage->execute($this->context(['db_name' => 'cust_unknown', 'db_user' => 'u_unknown']));

        $this->assertFalse($result->success);
        $this->assertSame([], $this->mutations($calls));
    }

    public function test_generated_names_keep_working_when_the_driver_cannot_answer(): void
    {
        $calls = [];
        $stage = $this->stage($calls, dbExists: null, userExists: null);

        $result = $stage->execute($this->context([]));

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame(['createDatabase', 'createUser', 'grantPrivileges'], array_column($this->mutations($calls), 0));
        $this->assertTrue($result->rollbackSnapshot['created_by_stage']);
    }

    public function test_free_custom_names_are_created_and_marked_as_owned_for_rollback(): void
    {
        $calls = [];
        $stage = $this->stage($calls, dbExists: false, userExists: false);

        $result = $stage->execute($this->context(['db_name' => 'cust_free', 'db_user' => 'u_free']));

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame(['createDatabase', 'cust_free'], $this->mutations($calls)[0]);
        $this->assertSame('cust_free', $result->rollbackSnapshot['db_name']);
        $this->assertTrue($result->rollbackSnapshot['created_by_stage']);
    }

    public function test_rollback_never_drops_names_the_stage_did_not_create(): void
    {
        $calls = [];
        $stage = $this->stage($calls, dbExists: false, userExists: false);
        $ctx = $this->context([]);

        $stage->rollback($ctx, ['db_name' => 'someone_elses', 'db_user' => 'their_user', 'created_by_stage' => false]);
        $this->assertSame([], $this->mutations($calls));

        $stage->rollback($ctx, ['db_name' => 'cust_mine', 'db_user' => 'u_mine', 'created_by_stage' => true]);
        $this->assertSame([['dropDatabase', 'cust_mine'], ['dropUser', 'u_mine']], $this->mutations($calls));
    }

    // --- fixtures -----------------------------------------------------------

    private function stage(array &$calls, ?bool $dbExists, ?bool $userExists): CreateDatabaseStage
    {
        $spy = new class($calls, $dbExists, $userExists) extends VpsDatabaseProvisioner {
            public function __construct(private array &$calls, private ?bool $dbExists, private ?bool $userExists) {}
            public function databaseExists(InstallationContext $ctx, string $dbName): ?bool { $this->calls[] = ['databaseExists', $dbName]; return $this->dbExists; }
            public function databaseUserExists(InstallationContext $ctx, string $dbUser): ?bool { $this->calls[] = ['databaseUserExists', $dbUser]; return $this->userExists; }
            public function createDatabase(InstallationContext $ctx, string $dbName): void { $this->calls[] = ['createDatabase', $dbName]; }
            public function createUser(InstallationContext $ctx, string $dbUser, string $dbPassword): void { $this->calls[] = ['createUser', $dbUser]; }
            public function grantPrivileges(InstallationContext $ctx, string $dbName, string $dbUser): void { $this->calls[] = ['grantPrivileges', $dbName, $dbUser]; }
            public function dropDatabase(InstallationContext $ctx, string $dbName): void { $this->calls[] = ['dropDatabase', $dbName]; }
            public function dropUser(InstallationContext $ctx, string $dbUser): void { $this->calls[] = ['dropUser', $dbUser]; }
        };

        return new CreateDatabaseStage(new DatabaseProvisionerFactory($spy, $this->createStub(CpanelDatabaseProvisioner::class)));
    }

    /** Calls that change the server (everything except the existence probes). */
    private function mutations(array $calls): array
    {
        return array_values(array_filter($calls, fn (array $c): bool => ! in_array($c[0], ['databaseExists', 'databaseUserExists'], true)));
    }

    private function context(array $overrides): InstallationContext
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Fake', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        $payload = array_merge([
            'company' => 'Guard Co', 'owner_name' => 'Owner', 'email' => 'guard@example.com',
            'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
            'currency' => 'IRR', 'domain' => 'guard.example.com', 'license_type' => 'basic',
            'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
            'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
        ], $overrides);

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Guard Co',
            'customer_email'    => 'guard@example.com',
            'customer_domain'   => 'guard.example.com',
            'request_payload'   => $payload,
        ]);

        return new InstallationContext($installation, CustomerInstallRequest::fromArray($payload), new FakeRemoteDriver());
    }
}
