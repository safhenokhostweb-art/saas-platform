<?php

namespace Tests\Unit\Services\CustomerInstallation;

use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationLog;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\InstallationStateManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 14-G1 — recordStageFailure()/markFailed() persist free-form error
 * strings verbatim into customer_installation_logs.message and
 * customer_installations.failure_reason, both rendered on the admin
 * installation detail page. VpsDatabaseProvisioner no longer embeds raw SQL
 * (which can contain a freshly-generated tenant DB password via
 * `IDENTIFIED BY '<password>'`) in its own exception message, but this test
 * exercises the second, defense-in-depth layer directly: even if some other
 * stage/command string ever produces a message shaped like a MySQL
 * IDENTIFIED BY clause or a MYSQL_PWD env assignment, it must not survive
 * into persisted, admin-visible state unredacted.
 */
class InstallationStateManagerFailureSanitizationTest extends TestCase
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
            'customer_company'  => 'Sanitize Co',
            'customer_email'    => 'sanitize@example.com',
            'customer_domain'   => 'sanitize.example.com',
            'request_payload'   => [
                'company' => 'Sanitize Co', 'owner_name' => 'Owner',
                'email' => 'sanitize@example.com', 'phone' => '09123456789',
                'language' => 'fa', 'timezone' => 'Asia/Tehran', 'currency' => 'IRR',
                'domain' => 'sanitize.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily',
                'smtp' => [], 'ssl_enabled' => false,
                'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);
    }

    public function test_record_stage_failure_redacts_identified_by_clause_from_the_log_message(): void
    {
        $installation = $this->makeInstallation();
        $leaky = "MySQL command failed (CREATE USER): CREATE USER IF NOT EXISTS 'u_x'@'localhost' IDENTIFIED BY 'S3cretPass1';\naccess denied";

        (new InstallationStateManager())->recordStageFailure($installation, 'CREATE_DATABASE', $leaky, 120);

        $log = CustomerInstallationLog::where('installation_id', $installation->id)
            ->where('stage', 'CREATE_DATABASE')
            ->where('event', 'failed')
            ->firstOrFail();

        $this->assertStringNotContainsString('S3cretPass1', $log->message);
        $this->assertStringContainsString("IDENTIFIED BY '[REDACTED]'", $log->message);
        // Non-secret context must still be present for debugging.
        $this->assertStringContainsString('MySQL command failed (CREATE USER)', $log->message);
    }

    public function test_mark_failed_redacts_identified_by_clause_from_failure_reason(): void
    {
        $installation = $this->makeInstallation();
        $leaky = "Stage CREATE_DATABASE failed: MySQL command failed (CREATE USER): "
            . "CREATE USER IF NOT EXISTS 'u_x'@'localhost' IDENTIFIED BY \"S3cretPass1\";";

        (new InstallationStateManager())->markFailed($installation, $leaky);

        $installation->refresh();

        $this->assertStringNotContainsString('S3cretPass1', $installation->failure_reason);
        $this->assertStringContainsString('IDENTIFIED BY "[REDACTED]"', $installation->failure_reason);
    }

    public function test_mark_failed_redacts_mysql_pwd_env_assignment(): void
    {
        $installation = $this->makeInstallation();

        (new InstallationStateManager())->markFailed(
            $installation,
            'MySQL command failed: exit 1, env MYSQL_PWD=hunter2secret was rejected'
        );

        $installation->refresh();

        $this->assertStringNotContainsString('hunter2secret', $installation->failure_reason);
        $this->assertStringContainsString('MYSQL_PWD=[REDACTED]', $installation->failure_reason);
    }

    public function test_ordinary_failure_messages_are_left_unchanged(): void
    {
        $installation = $this->makeInstallation();

        (new InstallationStateManager())->markFailed($installation, 'Stage CONFIGURE_WEB_SERVER failed: nginx -t failed');

        $installation->refresh();

        $this->assertSame('Stage CONFIGURE_WEB_SERVER failed: nginx -t failed', $installation->failure_reason);
    }
}
