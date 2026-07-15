<?php

namespace Tests\Feature\CustomerDeletion;

use App\Models\CustomerDeletion;
use App\Models\CustomerDeletionLog;
use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationLog;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerDeletion\CustomerDeleteOrchestrator;
use App\Services\CustomerInstallation\Contracts\RemoteConnectionInterface;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Dto\DriverCapabilities;
use App\Services\CustomerInstallation\Enums\DriverType;
use App\Services\CustomerInstallation\RemoteConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 14-G3 — full CustomerDeleteOrchestrator::run() integration coverage.
 *
 * A real 'cpanel'-type CustomerRemoteServer would build a genuine
 * CpanelDeploymentDriver (SSH transport) and attempt a real network
 * connection, so RemoteConnectionService is swapped for a test double that
 * hands back a FakeRemoteDriver shaped with the capabilities/driverType
 * under test — the connection itself never touches the network; UAPI calls
 * go through Http::fake().
 */
class DeletionHostingAbstractionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;

        parent::setUp();
    }

    private function bindFakeConnection(FakeRemoteDriver $driver): void
    {
        $service = new class($driver) extends RemoteConnectionService {
            public function __construct(private readonly FakeRemoteDriver $driver) {}
            public function buildDriver(CustomerRemoteServer $server): RemoteConnectionInterface
            {
                return $this->driver;
            }
        };

        $this->app->instance(RemoteConnectionService::class, $service);
    }

    private function makeDeletion(string $serverType): CustomerDeletion
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
            'customer_company'  => 'Full Pipeline Co',
            'customer_email'    => 'fullpipeline@example.com',
            'customer_domain'   => 'fullpipeline.example.com',
            'request_payload'   => [
                'company' => 'Full Pipeline Co', 'owner_name' => 'Owner', 'email' => 'fullpipeline@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'fullpipeline.example.com', 'license_type' => 'basic',
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

        // Policy DELETE_EVERYTHING skips FINAL_BACKUP entirely, keeping this
        // full-pipeline run focused on the hosting-abstraction behavior under
        // test rather than the backup credential path (covered separately in
        // FinalBackupStageCredentialTest).
        return CustomerDeletion::create([
            'installation_id' => $installation->id,
            'initiated_by'    => 1,
            'policy'          => 'delete_everything',
            'status'          => 'pending',
        ]);
    }

    public function test_cpanel_deletion_never_calls_mysql_root(): void
    {
        Http::fake([
            '*Mysql/delete_database' => Http::response(['status' => 1, 'data' => []], 200),
            '*Mysql/delete_user'     => Http::response(['status' => 1, 'data' => []], 200),
            '*Mysql/list_databases'  => Http::response(['status' => 1, 'data' => []], 200),
            '*Mysql/list_users'      => Http::response(['status' => 1, 'data' => []], 200),
            '*Cron/remove_line'      => Http::response(['status' => 1, 'data' => []], 200),
        ]);

        $driver = new FakeRemoteDriver(DriverCapabilities::forCpanel(), DriverType::CPANEL);
        $this->bindFakeConnection($driver);

        $deletion = $this->makeDeletion('cpanel');

        app(CustomerDeleteOrchestrator::class)->run($deletion);

        $deletion->refresh();
        $this->assertSame('complete', $deletion->status, $deletion->failure_reason ?? '');

        $commandLog = implode("\n", $driver->getCommandLog());
        $this->assertStringNotContainsString('mysql -u root', $commandLog);
        $this->assertStringNotContainsString('mysqldump -u root', $commandLog);
        $this->assertStringNotContainsString('/etc/supervisor', $commandLog);
        $this->assertStringNotContainsString('/etc/cron.d', $commandLog);
        $this->assertStringNotContainsString('/etc/nginx', $commandLog);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/execute/Mysql/delete_database'));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/execute/Mysql/delete_user'));
    }

    public function test_vps_deletion_behavior_is_unchanged(): void
    {
        $driver = new FakeRemoteDriver(); // full VPS capabilities, DriverType::FAKE
        $this->bindFakeConnection($driver);

        $deletion = $this->makeDeletion('fake');

        app(CustomerDeleteOrchestrator::class)->run($deletion);

        $deletion->refresh();
        $this->assertSame('complete', $deletion->status, $deletion->failure_reason ?? '');

        $commandLog = implode("\n", $driver->getCommandLog());
        $this->assertStringContainsString('mysql -u root', $commandLog);
        $this->assertStringContainsString('DROP DATABASE IF EXISTS `cust_db_1`;', $commandLog);
        $this->assertStringContainsString("DROP USER IF EXISTS 'u_deadbeef'@'localhost';", $commandLog);
        $this->assertStringContainsString('supervisorctl', $commandLog);
        $this->assertStringContainsString('/etc/nginx/sites-enabled/', $commandLog);
        $this->assertStringContainsString('nginx -s reload', $commandLog);
        $this->assertStringContainsString('/etc/cron.d/', $commandLog);
    }

    public function test_missing_capability_skips_correctly_without_aborting_the_pipeline(): void
    {
        // A capability set with everything OFF except what's required for the
        // pipeline to complete at all (database deletion, so REMOVE_DATABASE/
        // REMOVE_DATABASE_USER still exercise their own logic in this test).
        $capabilities = new DriverCapabilities(supportsDatabaseDeletion: true);
        $driver = new FakeRemoteDriver($capabilities, DriverType::CPANEL);
        $this->bindFakeConnection($driver);

        $deletion = $this->makeDeletion('cpanel');

        app(CustomerDeleteOrchestrator::class)->run($deletion);

        $deletion->refresh();
        $this->assertSame('complete', $deletion->status, $deletion->failure_reason ?? '');

        $skippedStages = CustomerDeletionLog::where('deletion_id', $deletion->id)
            ->where('event', 'skipped')
            ->pluck('stage')
            ->all();

        $this->assertContains('STOP_SERVICES', $skippedStages);
        $this->assertContains('REMOVE_WEBSERVER', $skippedStages);
        // No scheduler/queue snapshot was ever recorded for this installation,
        // so those stages also skip safely (see RemoveSchedulerQueueStagesTest).
        $this->assertContains('REMOVE_SCHEDULER', $skippedStages);
        $this->assertContains('REMOVE_QUEUE', $skippedStages);

        $commandLog = implode("\n", $driver->getCommandLog());
        $this->assertStringNotContainsString('/etc/supervisor', $commandLog);
        $this->assertStringNotContainsString('/etc/nginx', $commandLog);
        $this->assertStringNotContainsString('/etc/cron.d', $commandLog);
    }

    public function test_secret_never_appears_in_failure_logs_end_to_end(): void
    {
        // Force a failure whose underlying error text is shaped like a leaked
        // credential, by making the fake driver throw on the DB drop command
        // with a message containing an IDENTIFIED BY clause (as a
        // pre-14-G1/14-G3-hardening implementation would have surfaced).
        // REMOVE_DATABASE is non-critical by design (a stray failure there
        // must not abort the whole deletion — see CustomerDeleteOrchestrator's
        // docblock), so the pipeline still reaches 'complete'; what matters
        // here is that the recorded failure entry itself is sanitized.
        $driver = new class extends FakeRemoteDriver {
            public function execute(string $command, array $env = []): \App\Services\CustomerInstallation\Dto\CommandResult
            {
                if (str_contains($command, 'DROP DATABASE')) {
                    throw new \RuntimeException(
                        "MySQL command failed: CREATE USER 'u_x'@'localhost' IDENTIFIED BY 'Sup3rSecretLeak99'; access denied"
                    );
                }
                return parent::execute($command, $env);
            }
        };
        $this->bindFakeConnection($driver);

        $deletion = $this->makeDeletion('fake');

        app(CustomerDeleteOrchestrator::class)->run($deletion);

        $deletion->refresh();
        $this->assertSame('complete', $deletion->status, $deletion->failure_reason ?? '');

        $failureLog = CustomerDeletionLog::where('deletion_id', $deletion->id)
            ->where('stage', 'REMOVE_DATABASE')
            ->where('event', 'failed')
            ->firstOrFail();

        $this->assertStringNotContainsString('Sup3rSecretLeak99', $failureLog->message);
        $this->assertStringContainsString("IDENTIFIED BY '[REDACTED]'", $failureLog->message);

        $allLogText = CustomerDeletionLog::where('deletion_id', $deletion->id)->pluck('message')->implode(' | ');
        $this->assertStringNotContainsString('Sup3rSecretLeak99', $allLogText);
    }
}
