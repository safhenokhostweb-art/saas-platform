<?php

namespace Tests\Feature\CustomerInstallation;

use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerInstallationLog;
use App\Models\Site;
use App\Services\CustomerInstallation\CustomerInstallationOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class TerminalInstallerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private static int $sequence = 0;

    private function makeInstallation(array $overrides = []): CustomerInstallation
    {
        self::$sequence++;
        $customer = Customer::create([
            'name' => 'Installer Test Customer',
            'mobile' => '0912' . str_pad((string) self::$sequence, 7, '0', STR_PAD_LEFT),
            'email' => null,
            'company_name' => null,
            'notes' => null,
        ]);
        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'Installer Test Site',
            'domain' => 'installer-' . self::$sequence . '.example.test',
            'is_active' => false,
        ]);

        return CustomerInstallation::create(array_replace_recursive([
            'site_id' => $site->id,
            'remote_server_id' => 1,
            'installer_user_id' => 1,
            'install_mode' => 'MODE_FRESH',
            'status' => 'pending',
            'customer_company' => 'Installer Test',
            'customer_email' => 'installer@example.test',
            'customer_domain' => $site->domain,
            'completed_stages' => [],
            'request_payload' => [
                'app_url' => 'https://' . $site->domain,
                'domain' => $site->domain,
                'ssl_enabled' => true,
                'manual_db_credentials' => [
                    'host' => 'db.example.test',
                    'port' => '3306',
                    'database' => 'tenant_db',
                    'username' => 'tenant_user',
                    'password' => 'safe-test-password',
                    'validated' => true,
                ],
            ],
            'metadata' => [],
        ], $overrides));
    }

    private function seedPreparedInstallation(CustomerInstallation $installation, bool $includeExtract = true): void
    {
        $stages = ['UPLOAD_PACKAGE'];
        if ($includeExtract) {
            $stages[] = 'EXTRACT_PACKAGE';
        }
        $stages[] = 'GENERATE_ENVIRONMENT';
        $stages[] = 'PREPARE_DATABASE';

        $installation->update([
            'completed_stages' => $stages,
            'metadata' => ['database_state' => 'DB_READY'],
        ]);
        CustomerInstallationLog::create([
            'installation_id' => $installation->id,
            'stage' => 'GENERATE_ENVIRONMENT',
            'event' => 'completed',
            'message' => 'Environment generated for integration test',
            'output' => [
                'merge_performed' => true,
                'placeholder_keys' => [],
            ],
            'duration_ms' => 0,
            'created_at' => now(),
        ]);
    }

    private function invoke(
        CustomerInstallationOrchestrator $orchestrator,
        string $method,
        CustomerInstallation $installation,
    ): mixed {
        return (new ReflectionMethod($orchestrator, $method))->invoke($orchestrator, $installation);
    }

    public function test_success_path_reaches_customer_ready_without_duplicate_execution(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('', 200)]);

        $installation = $this->makeInstallation();
        $this->seedPreparedInstallation($installation);
        $orchestrator = app(CustomerInstallationOrchestrator::class);

        $this->assertTrue($this->invoke($orchestrator, 'runHostingInstallerFinalization', $installation));
        $installation->refresh();
        $this->assertSame('INSTALLATION_READY', $installation->status);

        $this->invoke($orchestrator, 'runHostingHttpVerification', $installation);
        $installation->refresh();
        $this->assertSame('VERIFY_SUCCESS', $installation->status);

        $this->invoke($orchestrator, 'persistVerifiedInstallationState', $installation);
        $installation->refresh();
        $completedAt = $installation->completed_at?->toISOString();
        $logCount = $installation->logs()->count();

        $this->assertSame('CUSTOMER_READY', $installation->status);
        $this->assertSame('CUSTOMER_READY', data_get($installation->metadata, 'installation_state'));
        $this->assertNotNull($completedAt);
        $this->assertTrue($installation->site_id !== null);
        $this->assertTrue(Site::findOrFail($installation->site_id)->is_active);
        $this->assertSame([
            'UPLOAD_PACKAGE',
            'EXTRACT_PACKAGE',
            'GENERATE_ENVIRONMENT',
            'PREPARE_DATABASE',
            'RUN_INSTALLER',
            'VERIFY_HTTP',
            'CUSTOMER_READY',
        ], $installation->completedStagesList());
        Http::assertSentCount(1);

        $this->invoke($orchestrator, 'persistVerifiedInstallationState', $installation);
        $this->invoke($orchestrator, 'persistVerifiedInstallationState', $installation);
        $installation->refresh();

        $this->assertSame(1, CustomerInstallation::whereKey($installation->id)->count());
        $this->assertSame(1, Site::whereKey($installation->site_id)->count());
        $this->assertSame($completedAt, $installation->completed_at?->toISOString());
        $this->assertSame($logCount, $installation->logs()->count());
        $this->assertSame(1, count(array_keys(
            $installation->completedStagesList(),
            'CUSTOMER_READY',
            true,
        )));
        Http::assertSentCount(1);
    }

    public function test_missing_database_credentials_pause_without_partial_commit(): void
    {
        $installation = $this->makeInstallation([
            'request_payload' => ['manual_db_credentials' => null],
        ]);
        $orchestrator = app(CustomerInstallationOrchestrator::class);

        $this->assertFalse($this->invoke($orchestrator, 'runHostingDatabaseGate', $installation));
        $installation->refresh();

        $this->assertSame('DB_FAILED', data_get($installation->metadata, 'database_state'));
        $this->assertSame('INSTALLATION_PAUSED_DB_PENDING', $installation->failure_reason);
        $this->assertNull($installation->completed_at);
        $this->assertFalse(Site::findOrFail($installation->site_id)->is_active);
        $this->assertFalse($installation->hasCompletedStage('CUSTOMER_READY'));
    }

    public function test_invalid_archive_state_blocks_finalization_without_orphaning_site(): void
    {
        $installation = $this->makeInstallation();
        $this->seedPreparedInstallation($installation, includeExtract: false);
        $orchestrator = app(CustomerInstallationOrchestrator::class);

        $this->assertFalse($this->invoke($orchestrator, 'runHostingInstallerFinalization', $installation));
        $installation->refresh();

        $this->assertSame('INSTALLATION_BLOCKED', $installation->status);
        $this->assertContains(
            'extract_package_not_completed',
            data_get($installation->metadata, 'precondition_errors', []),
        );
        $this->assertNull($installation->completed_at);
        $this->assertFalse(Site::findOrFail($installation->site_id)->is_active);
        $this->assertFalse($installation->hasCompletedStage('CUSTOMER_READY'));
    }

    public function test_failed_http_verification_persists_failed_without_ready_commit(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('', 500)]);

        $installation = $this->makeInstallation([
            'status' => 'running',
            'completed_stages' => [
                'UPLOAD_PACKAGE',
                'EXTRACT_PACKAGE',
                'GENERATE_ENVIRONMENT',
                'PREPARE_DATABASE',
                'RUN_INSTALLER',
            ],
            'metadata' => [
                'database_state' => 'DB_READY',
                'installation_state' => 'INSTALLATION_READY',
            ],
        ]);
        $orchestrator = app(CustomerInstallationOrchestrator::class);

        $this->invoke($orchestrator, 'runHostingHttpVerification', $installation);
        $this->invoke($orchestrator, 'persistVerifiedInstallationState', $installation);
        $installation->refresh();

        $this->assertSame('FAILED', $installation->status);
        $this->assertSame('FAILED', data_get($installation->metadata, 'installation_state'));
        $this->assertNull($installation->completed_at);
        $this->assertFalse(Site::findOrFail($installation->site_id)->is_active);
        $this->assertFalse($installation->hasCompletedStage('CUSTOMER_READY'));
        Http::assertSentCount(1);
    }

    public function test_database_status_contract_supports_terminal_state_machine(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL ENUM contract test.');
        }

        $column = DB::selectOne(
            'SHOW COLUMNS FROM customer_installations WHERE Field = ?',
            ['status'],
        );
        $type = (string) ($column->Type ?? '');

        foreach ([
            'INSTALLATION_READY',
            'INSTALLATION_BLOCKED',
            'VERIFY_SUCCESS',
            'VERIFY_FAILED',
            'CUSTOMER_READY',
            'FAILED',
        ] as $requiredState) {
            $this->assertStringContainsString(
                chr(39) . $requiredState . chr(39),
                $type,
                'customer_installations.status ENUM is missing ' . $requiredState,
            );
        }
    }
}
