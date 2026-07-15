<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Stages\ConfigureStorageStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 14-F5 — ConfigureStorageStage's own execute() logic is unchanged
 * (Part 2 explicitly forbids touching it); these tests pin that the
 * pre-existing storage:link / fallback ln -sf / genuine-failure behavior
 * still works exactly as before the capability gate was added.
 */
class ConfigureStorageStageTest extends TestCase
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
            'label' => 'Fake', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        $installation = CustomerInstallation::create([
            'remote_server_id'  => $server->id,
            'installer_user_id' => 1,
            'install_mode'      => 'MODE_FRESH',
            'status'            => 'running',
            'customer_company'  => 'Storage Stage Co',
            'customer_email'    => 'storage@example.com',
            'customer_domain'   => 'storage.example.com',
            'request_payload'   => [
                'company' => 'Storage Stage Co', 'owner_name' => 'Owner', 'email' => 'storage@example.com',
                'phone' => '09123456789', 'language' => 'fa', 'timezone' => 'Asia/Tehran',
                'currency' => 'IRR', 'domain' => 'storage.example.com', 'license_type' => 'basic',
                'theme_slug' => '', 'modules' => [], 'backup_policy' => 'daily', 'smtp' => [],
                'ssl_enabled' => false, 'remote_server_id' => $server->id, 'install_mode' => 'MODE_FRESH',
            ],
        ]);

        $request = CustomerInstallRequest::fromArray($installation->request_payload);

        return new InstallationContext($installation, $request, $driver);
    }

    public function test_execute_creates_symlink_via_artisan_and_succeeds(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('test -L', 0, 'linked');
        $ctx = $this->context($driver);

        $result = (new ConfigureStorageStage())->execute($ctx);

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertTrue($result->output['storage_configured']);

        $log = $driver->getCommandLog();
        $this->assertStringContainsString('mkdir -p', $log[0]);
        $this->assertStringContainsString('artisan storage:link', $log[1]);
    }

    /**
     * FakeRemoteDriver returns the same canned response for every call
     * matching a pattern, so both "test -L" checks (before and after the
     * manual "ln -sf" fallback) report "missing" here — meaning this
     * fixture exercises the fallback attempt AND the still-failing-after-
     * fallback path together. Confirms two things: the manual ln -sf
     * command is genuinely issued (not skipped), and an unrecovered
     * failure remains a real StageResult::failure — not silently skipped —
     * exactly as before this phase's capability gate was added.
     */
    public function test_execute_attempts_manual_fallback_and_fails_when_still_unrecovered(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('test -L', 1, 'missing');
        $ctx = $this->context($driver);

        $result = (new ConfigureStorageStage())->execute($ctx);

        $log = $driver->getCommandLog();
        $fallbackIssued = array_filter($log, fn ($command) => str_contains($command, 'ln -sf'));
        $this->assertNotEmpty($fallbackIssued, 'The manual "ln -sf" fallback must actually be issued, not skipped');
        $this->assertFalse($result->success, 'A genuine, unrecovered symlink failure must remain a real StageResult::failure — not a skip');
        $this->assertStringContainsString('Storage symlink creation failed', $result->error);
    }

    public function test_required_capabilities_declares_storage_link(): void
    {
        $stage = new ConfigureStorageStage();

        $this->assertSame(['supportsStorageLink'], $stage->requiredCapabilities());
        $this->assertFalse($stage->isCapabilityRequiredToProceed());
    }
}
