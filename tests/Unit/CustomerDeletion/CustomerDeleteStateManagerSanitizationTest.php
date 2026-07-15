<?php

namespace Tests\Unit\CustomerDeletion;

use App\Models\CustomerDeletion;
use App\Models\CustomerDeletionLog;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerDeletion\CustomerDeleteStateManager;
use App\Services\CustomerInstallation\CredentialVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 14-G3 — mirrors the Phase 14-G1 defense-in-depth redaction added to
 * InstallationStateManager: recordStageFailure()/markFailed() strip
 * credential-shaped fragments (IDENTIFIED BY '...', MYSQL_PWD=...) before
 * persisting into customer_deletion_logs.message / failure_reason, both
 * rendered on the admin deletion detail page.
 */
class CustomerDeleteStateManagerSanitizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;

        parent::setUp();
    }

    private function makeDeletion(): CustomerDeletion
    {
        $server = CustomerRemoteServer::create([
            'label' => 'S', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'complete',
            'customer_company'  => 'Sanitize Del Co',
            'customer_email'    => 'sanitizedel@example.com',
            'customer_domain'   => 'sanitizedel.example.com',
            'request_payload'   => [
                'company' => 'Sanitize Del Co', 'owner_name' => 'Owner', 'email' => 'sanitizedel@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'sanitizedel.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        return CustomerDeletion::create([
            'installation_id' => $installation->id,
            'initiated_by'    => 1,
            'policy'          => 'never_delete_backup',
            'status'          => 'running',
        ]);
    }

    public function test_record_stage_failure_redacts_identified_by_clause(): void
    {
        $deletion = $this->makeDeletion();
        $leaky = "DROP USER failed: CREATE USER IF NOT EXISTS 'u_x'@'localhost' IDENTIFIED BY 'S3cretPass1';";

        (new CustomerDeleteStateManager())->recordStageFailure($deletion, 'REMOVE_DATABASE_USER', $leaky, 50);

        $log = CustomerDeletionLog::where('deletion_id', $deletion->id)
            ->where('stage', 'REMOVE_DATABASE_USER')
            ->where('event', 'failed')
            ->firstOrFail();

        $this->assertStringNotContainsString('S3cretPass1', $log->message);
        $this->assertStringContainsString("IDENTIFIED BY '[REDACTED]'", $log->message);
    }

    public function test_mark_failed_redacts_mysql_pwd_assignment(): void
    {
        $deletion = $this->makeDeletion();

        (new CustomerDeleteStateManager())->markFailed(
            $deletion,
            'mysqldump پشتیبان نهایی ناموفق بود: env MYSQL_PWD=hunter2secret rejected'
        );

        $deletion->refresh();

        $this->assertStringNotContainsString('hunter2secret', $deletion->failure_reason);
        $this->assertStringContainsString('MYSQL_PWD=[REDACTED]', $deletion->failure_reason);
    }

    public function test_ordinary_failure_messages_are_left_unchanged(): void
    {
        $deletion = $this->makeDeletion();

        (new CustomerDeleteStateManager())->markFailed($deletion, 'mysqldump پشتیبان نهایی ناموفق بود');

        $deletion->refresh();

        $this->assertSame('mysqldump پشتیبان نهایی ناموفق بود', $deletion->failure_reason);
    }
}
