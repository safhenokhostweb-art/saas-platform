<?php

namespace Tests\Unit\CustomerDeletion;

use App\Models\Customer;
use App\Models\CustomerDeletion;
use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationLog;
use App\Models\CustomerRemoteServer;
use App\Models\Site;
use App\Services\CustomerDeletion\Dto\DeleteContext;
use App\Services\CustomerDeletion\Stages\FinalBackupStage;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\Infrastructure\ArvanBackupService;
use App\Services\Installer\LocalRecoveryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Phase 14-G3 — FinalBackupStage no longer runs `mysqldump -u root` (a hard
 * cPanel blocker, since this stage is critical). It authenticates as the
 * customer's own DB user: Site credential (db_password_encrypted) first,
 * remote-.env grep fallback (the BackupModuleUpgradeStage precedent), and a
 * clear secret-free failure when neither source has a credential. The
 * password travels via the connection's env mechanism and never appears in
 * any command string, message, or output.
 */
class FinalBackupStageCredentialTest extends TestCase
{
    use RefreshDatabase;

    private const DB_PASSWORD = 'Sup3rSecretDbPass_14G3';

    protected function setUp(): void
    {
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;

        parent::setUp();

        Carbon::setTestNow('2026-07-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeDeletion(bool $withSitePassword): CustomerDeletion
    {
        $server = CustomerRemoteServer::create([
            'label' => 'S', 'type' => 'fake', 'host' => 'box.example.test',
            'port' => 22, 'username' => 'johndoe', 'auth_method' => 'password',
            'password_encrypted' => (new CredentialVault())->encrypt('the-ssh-password'),
        ]);

        $customer = Customer::create(['name' => 'Backup Customer', 'mobile' => '09120000000']);

        $site = Site::create([
            'customer_id'           => $customer->id,
            'title'                 => 'Backup Site',
            'domain'                => 'finalbackup.example.com',
            'db_name'               => 'cust_db_1',
            'db_user'               => 'u_deadbeef',
            'db_password_encrypted' => $withSitePassword
                ? (new CredentialVault())->encrypt(self::DB_PASSWORD)
                : null,
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'site_id'           => $site->id,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'complete',
            'customer_company'  => 'Final Backup Co',
            'customer_email'    => 'finalbackup@example.com',
            'customer_domain'   => 'finalbackup.example.com',
            'request_payload'   => [
                'company' => 'Final Backup Co', 'owner_name' => 'Owner', 'email' => 'finalbackup@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'finalbackup.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

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
            'site_id'         => $site->id,
            'initiated_by'    => 1,
            'policy'          => 'never_delete_backup',
            'status'          => 'running',
        ]);
    }

    private function stage(): FinalBackupStage
    {
        $localRepo = $this->createMock(LocalRecoveryRepository::class);
        $localRepo->method('shouldUseLocal')->willReturn(true);
        $localRepo->method('shouldUseArvan')->willReturn(false);
        $localRepo->method('storeCustomerBaseline')->willReturn([
            'success' => true, 'skipped' => false, 'local_path' => '/recovery/final.sql.gz',
        ]);

        return new FinalBackupStage($this->createMock(ArvanBackupService::class), $localRepo);
    }

    /** Prime the fake driver so the dump/stat/download flow succeeds. */
    private function primeHappyPath(FakeRemoteDriver $driver, DeleteContext $ctx): void
    {
        $driver->addResponse('mysqldump', 0, 'ok');
        $driver->addResponse('stat -c%s', 0, '5000');

        $backupFile = "/tmp/{$ctx->slug}_final_backup_" . now()->format('Ymd_His') . '.sql.gz';
        $driver->putFile($backupFile);
    }

    public function test_uses_the_site_credential_and_never_root_mysql(): void
    {
        $driver = new FakeRemoteDriver();
        $ctx = DeleteContext::fromDeletion($this->makeDeletion(withSitePassword: true), $driver);
        $this->primeHappyPath($driver, $ctx);

        $result = $this->stage()->execute($ctx);

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertSame('site_credential', $result->output['credential_source']);

        $log = implode("\n", $driver->getCommandLog());
        $this->assertStringContainsString('mysqldump -u', $log);
        $this->assertStringNotContainsString('mysqldump -u root', $log);
        $this->assertStringNotContainsString('-u root', $log);
        $this->assertStringNotContainsString(self::DB_PASSWORD, $log, 'the password must never enter a command string');
        $this->assertStringNotContainsString(self::DB_PASSWORD, json_encode($result->output));
    }

    public function test_falls_back_to_the_remote_env_credential(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse("grep '^DB_USERNAME='", 0, 'u_deadbeef');
        $driver->addResponse("grep '^DB_PASSWORD='", 0, self::DB_PASSWORD);

        $ctx = DeleteContext::fromDeletion($this->makeDeletion(withSitePassword: false), $driver);
        $this->primeHappyPath($driver, $ctx);

        $result = $this->stage()->execute($ctx);

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertSame('remote_env', $result->output['credential_source']);

        $log = implode("\n", $driver->getCommandLog());
        $this->assertStringNotContainsString('-u root', $log);
        $this->assertStringNotContainsString(self::DB_PASSWORD, $log);
    }

    public function test_fails_clearly_and_without_secrets_when_no_credential_exists(): void
    {
        // No Site password, and the remote .env greps return nothing.
        $driver = new FakeRemoteDriver();
        $ctx = DeleteContext::fromDeletion($this->makeDeletion(withSitePassword: false), $driver);

        $result = $this->stage()->execute($ctx);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('اعتبارنامهٔ دیتابیس مشتری', $result->error);
        $this->assertStringNotContainsString(self::DB_PASSWORD, $result->error);

        $log = implode("\n", $driver->getCommandLog());
        $this->assertStringNotContainsString('mysqldump', $log, 'no dump may run without a credential');
    }

    public function test_stage_remains_critical(): void
    {
        $this->assertTrue($this->stage()->isCritical(), 'a failed final backup must abort the deletion pipeline');
    }
}
