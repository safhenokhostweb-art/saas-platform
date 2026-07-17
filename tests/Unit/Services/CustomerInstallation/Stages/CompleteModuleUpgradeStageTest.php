<?php

namespace Tests\Unit\Services\CustomerInstallation\Stages;

use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Module;
use App\Models\Site;
use App\Services\CustomerInstallation\Dto\CustomerInstallRequest;
use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\ModuleUpdateTarget;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\CustomerInstallation\Stages\CompleteModuleUpgradeStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 13-I — CompleteModuleUpgradeStage scenarios. Reuses
 * PostInstallValidationService unmodified — these tests exercise it
 * through the same remote-command shape ValidateDiskStage/ExtractPackageStage
 * tests already use (FakeRemoteDriver::addResponse).
 */
class CompleteModuleUpgradeStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function makeContext(FakeRemoteDriver $driver, array $overrides = []): InstallationContext
    {
        $server = CustomerRemoteServer::create([
            'label' => 'Test Server', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        $installation = CustomerInstallation::create(array_merge([
            'remote_server_id' => $server->id,
            'installer_user_id' => 1,
            'install_mode' => 'MODE_MODULE_UPDATE',
            'status' => 'running',
            'customer_company' => 'Test Co',
            'customer_email' => 'test@example.com',
            'customer_domain' => 'test.example.com',
            'request_payload' => [
                'company' => 'Test Co', 'owner_name' => 'Owner', 'email' => 'test@example.com',
                'phone' => '09123456789', 'domain' => 'test.example.com',
                'remote_server_id' => $server->id, 'install_mode' => 'MODE_MODULE_UPDATE',
            ],
        ], $overrides));

        $request = CustomerInstallRequest::fromArray($installation->request_payload);
        $target = new ModuleUpdateTarget('forms', 1, '1.1.0');

        return new InstallationContext($installation, $request, $driver, $target);
    }

    /**
     * @return array{site: Site, module: Module}
     */
    private function makeSiteWithModule(string $installed, string $slug = 'forms'): array
    {
        $customer = Customer::create([
            'name' => 'مشتری تست', 'mobile' => '0912' . random_int(1000000, 9999999),
        ]);

        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت تست',
            'domain' => 'test-' . uniqid('', true) . '.example.com',
            'is_active' => true,
        ]);

        $module = Module::updateOrCreate(
            ['slug' => $slug],
            ['title' => 'ماژول فرم', 'is_active' => true, 'version' => $installed, 'type' => Module::TYPE_CORE]
        );

        $site->modules()->attach($module->id, [
            'is_active' => true,
            'installed_version' => $installed,
            'version_reported_at' => now()->subDays(3),
        ]);

        return compact('site', 'module');
    }

    private function fetchSiteModuleRow(int $siteId, int $moduleId): ?object
    {
        return DB::table('site_modules')
            ->where('site_id', $siteId)
            ->where('module_id', $moduleId)
            ->first();
    }

    public function test_marks_complete_when_required_checks_pass(): void
    {
        $driver = new FakeRemoteDriver();
        $driver->addResponse('public/storage', 0, "linked\n");
        $driver->addResponse('.cie_health', 0, "writable\n");

        $ctx = $this->makeContext($driver);
        $result = app(CompleteModuleUpgradeStage::class)->execute($ctx);

        $this->assertTrue($result->success);
        $ctx->installation->refresh();
        $this->assertSame('complete', $ctx->installation->status);
        $this->assertNotNull($ctx->installation->post_install_validation);
    }

    public function test_fails_and_does_not_mark_complete_when_storage_check_fails(): void
    {
        // No storage-symlink response registered — defaults to "missing" (required, FAIL).
        $driver = new FakeRemoteDriver();

        $ctx = $this->makeContext($driver);
        $result = app(CompleteModuleUpgradeStage::class)->execute($ctx);

        $this->assertFalse($result->success);
        $ctx->installation->refresh();
        $this->assertNotSame('complete', $ctx->installation->status);
    }

    // ─── Phase 13-M4 — Version Sync ─────────────────────────────────────────

    /**
     * Scenario 1 — successful upgrade syncs site_modules.installed_version,
     * without touching version_reported_at (provenance stays with the
     * tenant's own self-report).
     */
    public function test_syncs_installed_version_on_success(): void
    {
        ['site' => $site, 'module' => $module] = $this->makeSiteWithModule('1.0.0');
        $before = $this->fetchSiteModuleRow($site->id, $module->id);

        $driver = new FakeRemoteDriver();
        $driver->addResponse('public/storage', 0, "linked\n");
        $driver->addResponse('.cie_health', 0, "writable\n");

        $ctx = $this->makeContext($driver, [
            'site_id' => $site->id,
            'metadata' => ['module_slug' => $module->slug, 'target_version' => '1.1.0'],
        ]);

        $result = app(CompleteModuleUpgradeStage::class)->execute($ctx);

        $this->assertTrue($result->success);

        $after = $this->fetchSiteModuleRow($site->id, $module->id);
        $this->assertSame('1.1.0', $after->installed_version);
        // version_reported_at must be untouched — only the tenant's own
        // self-report (recordReportedVersions) is allowed to set it.
        $this->assertEquals($before->version_reported_at, $after->version_reported_at);
    }

    /**
     * Phase 17-C — Part 5: a successful upgrade also closes the Entitlement
     * vs. Deployment gap — deployment_status flips to 'installed' and
     * last_installation_id points at the installation that did it.
     */
    public function test_syncs_deployment_status_and_last_installation_id_on_success(): void
    {
        ['site' => $site, 'module' => $module] = $this->makeSiteWithModule('1.0.0');
        DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $module->id)
            ->update(['deployment_status' => 'upgrading']);

        $driver = new FakeRemoteDriver();
        $driver->addResponse('public/storage', 0, "linked\n");
        $driver->addResponse('.cie_health', 0, "writable\n");

        $ctx = $this->makeContext($driver, [
            'site_id' => $site->id,
            'metadata' => ['module_slug' => $module->slug, 'target_version' => '1.1.0'],
        ]);

        $result = app(CompleteModuleUpgradeStage::class)->execute($ctx);

        $this->assertTrue($result->success);

        $after = $this->fetchSiteModuleRow($site->id, $module->id);
        $this->assertSame('installed', $after->deployment_status);
        $this->assertSame($ctx->installation->id, $after->last_installation_id);
    }

    /**
     * Phase 17-C — Part 4/5: MODE_MODULE_INSTALL (a module's first-ever
     * deployment) syncs identically to MODE_MODULE_UPDATE — same stage,
     * same sync call.
     */
    public function test_syncs_deployment_status_for_a_fresh_module_install(): void
    {
        ['site' => $site, 'module' => $module] = $this->makeSiteWithModule('1.0.0');

        $driver = new FakeRemoteDriver();
        $driver->addResponse('public/storage', 0, "linked\n");
        $driver->addResponse('.cie_health', 0, "writable\n");

        $ctx = $this->makeContext($driver, [
            'install_mode' => 'MODE_MODULE_INSTALL',
            'site_id' => $site->id,
            'metadata' => ['module_slug' => $module->slug, 'target_version' => '1.0.0'],
        ]);

        $result = app(CompleteModuleUpgradeStage::class)->execute($ctx);

        $this->assertTrue($result->success);

        $after = $this->fetchSiteModuleRow($site->id, $module->id);
        $this->assertSame('installed', $after->deployment_status);
        $this->assertSame($ctx->installation->id, $after->last_installation_id);
    }

    /**
     * Scenario 2 — incomplete metadata (target_version missing) must not
     * fail the stage; it just skips the sync and logs a warning.
     */
    public function test_does_not_fail_when_metadata_incomplete(): void
    {
        ['site' => $site, 'module' => $module] = $this->makeSiteWithModule('1.0.0');

        $driver = new FakeRemoteDriver();
        $driver->addResponse('public/storage', 0, "linked\n");
        $driver->addResponse('.cie_health', 0, "writable\n");

        $ctx = $this->makeContext($driver, [
            'site_id' => $site->id,
            'metadata' => ['module_slug' => $module->slug], // target_version missing
        ]);

        $result = app(CompleteModuleUpgradeStage::class)->execute($ctx);

        $this->assertTrue($result->success);
        $ctx->installation->refresh();
        $this->assertSame('complete', $ctx->installation->status);

        // installed_version stays exactly as it was before the (skipped) sync.
        $after = $this->fetchSiteModuleRow($site->id, $module->id);
        $this->assertSame('1.0.0', $after->installed_version);
    }

    /**
     * Scenario 3 — non-MODE_MODULE_UPDATE installations never sync a
     * version, even if site_id/metadata happen to be present.
     */
    public function test_does_not_sync_version_for_non_module_update_mode(): void
    {
        ['site' => $site, 'module' => $module] = $this->makeSiteWithModule('1.0.0');

        $driver = new FakeRemoteDriver();
        $driver->addResponse('public/storage', 0, "linked\n");
        $driver->addResponse('.cie_health', 0, "writable\n");

        $ctx = $this->makeContext($driver, [
            'install_mode' => 'MODE_FRESH',
            'site_id' => $site->id,
            'metadata' => ['module_slug' => $module->slug, 'target_version' => '1.1.0'],
        ]);

        $result = app(CompleteModuleUpgradeStage::class)->execute($ctx);

        $this->assertTrue($result->success);

        $after = $this->fetchSiteModuleRow($site->id, $module->id);
        $this->assertSame('1.0.0', $after->installed_version);
    }
}
