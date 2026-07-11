<?php

namespace Tests\Unit\Services\CustomerInstallation;

use App\Models\CustomerInstallation;
use App\Models\CustomerInstallRollback;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\InstallationStateManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 13-O5 — InstallationStateManager::recordRollbackCheckpoint() is the
 * real persistence path every Stage's snapshot goes through before rollback
 * ever runs. These tests go through that public method (not a direct call
 * to the private sanitizeSnapshot(), and not a hand-built
 * CustomerInstallRollback::create() that bypasses it, which is how the
 * Phase 13-O4 audit found the previous allowlist bug had gone undetected)
 * so a regression here would be caught the same way the original bug
 * should have been.
 */
class InstallationStateManagerSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function makeInstallation(): CustomerInstallation
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Fake', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        return CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Snapshot Co',
            'customer_email'    => 'snapshot@example.com',
            'customer_domain'   => 'snapshot.example.com',
            'request_payload'   => [
                'company' => 'Snapshot Co', 'owner_name' => 'Owner',
                'email' => 'snapshot@example.com', 'phone' => '09123456789',
                'language' => 'fa', 'timezone' => 'Asia/Tehran', 'currency' => 'IRR',
                'domain' => 'snapshot.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily',
                'smtp' => [], 'ssl_enabled' => false,
                'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);
    }

    private function persistedSnapshot(CustomerInstallation $installation, string $stage): array
    {
        return CustomerInstallRollback::where('installation_id', $installation->id)
            ->where('stage', $stage)
            ->whereNull('executed_at')
            ->firstOrFail()
            ->snapshot;
    }

    public function test_backup_module_upgrade_snapshot_is_preserved_in_full(): void
    {
        $installation = $this->makeInstallation();
        $snapshot = [
            'backup_sha256'    => str_repeat('a', 64),
            'backup_arvan_key' => 'customers/site-1/module-upgrades/backup_x.sql.gz',
            'local_repo_file'  => '/var/backups/customer_baseline.sql.gz',
        ];

        (new InstallationStateManager())->recordRollbackCheckpoint($installation, 'BACKUP_MODULE_UPGRADE', $snapshot);

        // assertEquals, not assertSame: MySQL's JSON column type doesn't
        // guarantee preserving key insertion order on round-trip, so this
        // compares key/value pairs regardless of order.
        $this->assertEquals($snapshot, $this->persistedSnapshot($installation, 'BACKUP_MODULE_UPGRADE'));
    }

    public function test_extract_module_package_snapshot_is_preserved_in_full(): void
    {
        $installation = $this->makeInstallation();
        $snapshot = [
            'module_path' => '/var/www/tenant/modules/forms',
            'backup_path' => '/var/www/tenant/modules/forms.bak_20260711',
        ];

        (new InstallationStateManager())->recordRollbackCheckpoint($installation, 'EXTRACT_MODULE_PACKAGE', $snapshot);

        $this->assertEquals($snapshot, $this->persistedSnapshot($installation, 'EXTRACT_MODULE_PACKAGE'));
    }

    public function test_configure_dns_snapshot_is_preserved_in_full(): void
    {
        $installation = $this->makeInstallation();
        $snapshot = [
            'record_id'   => 'dns-record-123',
            'subdomain'   => 'tenant1',
            'fqdn'        => 'tenant1.safhenokhost.ir',
            'root_domain' => 'safhenokhost.ir',
        ];

        (new InstallationStateManager())->recordRollbackCheckpoint($installation, 'CONFIGURE_DNS', $snapshot);

        $this->assertEquals($snapshot, $this->persistedSnapshot($installation, 'CONFIGURE_DNS'));
    }

    public function test_fresh_install_stage_keys_still_persist_unchanged(): void
    {
        $installation = $this->makeInstallation();
        $snapshot = [
            'remote_path' => '/var/www/tenant',
            'db_name'     => 'cust_ab12cd_5',
            'db_user'     => 'u_xyz12345',
            'config_path' => '/etc/nginx/sites-available/tenant1',
            'cron_path'   => '/etc/cron.d/tenant1',
            'site_id'     => 42,
            'license_id'  => 7,
        ];

        (new InstallationStateManager())->recordRollbackCheckpoint($installation, 'UPLOAD_PACKAGE', $snapshot);

        $this->assertEquals($snapshot, $this->persistedSnapshot($installation, 'UPLOAD_PACKAGE'));
    }

    public function test_sensitive_looking_keys_are_stripped(): void
    {
        $installation = $this->makeInstallation();

        (new InstallationStateManager())->recordRollbackCheckpoint($installation, 'GENERATE_ENVIRONMENT', [
            'env_path'        => '/var/www/tenant/.env',
            'admin_password'  => 'plaintext-should-not-persist',
            'ssh_private_key' => '-----BEGIN PRIVATE KEY-----should-not-persist',
            'api_token'       => 'should-not-persist',
            'db_secret'       => 'should-not-persist',
            'credential_blob' => 'should-not-persist',
            'arvan_api_key'   => 'should-not-persist',
        ]);

        $persisted = $this->persistedSnapshot($installation, 'GENERATE_ENVIRONMENT');

        $this->assertSame(['env_path' => '/var/www/tenant/.env'], $persisted);
        $this->assertArrayNotHasKey('admin_password', $persisted);
        $this->assertArrayNotHasKey('ssh_private_key', $persisted);
        $this->assertArrayNotHasKey('api_token', $persisted);
        $this->assertArrayNotHasKey('db_secret', $persisted);
        $this->assertArrayNotHasKey('credential_blob', $persisted);
        $this->assertArrayNotHasKey('arvan_api_key', $persisted);
    }

    public function test_sensitive_keys_are_stripped_even_when_nested(): void
    {
        $installation = $this->makeInstallation();

        (new InstallationStateManager())->recordRollbackCheckpoint($installation, 'SOME_FUTURE_STAGE', [
            'module_path' => '/var/www/tenant/modules/forms',
            'auth'        => [
                'api_token' => 'should-not-persist',
                'note'      => 'kept',
            ],
        ]);

        $persisted = $this->persistedSnapshot($installation, 'SOME_FUTURE_STAGE');

        $this->assertEquals([
            'module_path' => '/var/www/tenant/modules/forms',
            'auth' => ['note' => 'kept'],
        ], $persisted);
    }
}
