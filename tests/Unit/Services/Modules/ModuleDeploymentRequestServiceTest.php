<?php

namespace Tests\Unit\Services\Modules;

use App\Jobs\CustomerInstallationPipelineJob;
use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Module;
use App\Models\ModuleLicense;
use App\Models\ModuleRelease;
use App\Models\Site;
use App\Models\User;
use App\Services\ModuleAssignmentService;
use App\Services\Modules\ModuleDeploymentRequestService;
use App\Services\OrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 17-E — Part 5: ModuleDeploymentRequestService scenarios. Replaces
 * ModuleInstallationDispatcherTest (deleted — that class no longer exists;
 * every scenario it covered is re-verified here against the consolidated
 * service). Mirrors the fixture shape used throughout the 17-C/17-D suite
 * (real "forms" package, a MODE_FRESH-complete installation to resolve the
 * remote server from).
 */
class ModuleDeploymentRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'forms';

    private function service(): ModuleDeploymentRequestService
    {
        // Phase 17-F — Part 4/5: resolve via the container instead of
        // manual `new` so the newly added PlatformCompatibilityService and
        // ModuleDeploymentDependencyResolver constructor dependencies are
        // wired automatically — this test isn't about those checks
        // specifically (see ModuleCompatibilityInstallTest /
        // ModuleDeploymentDependencyResolverTest for that), so it should
        // never need updating again just because the constructor grows.
        return app(ModuleDeploymentRequestService::class);
    }

    private function makeSite(): array
    {
        $customer = Customer::create([
            'name' => 'مشتری تست', 'mobile' => '0912' . random_int(1000000, 9999999),
        ]);

        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت تست',
            'domain' => 'test-' . uniqid('', true) . '.example.com',
            'is_active' => true,
            'support_expires_at' => now()->addYear(),
        ]);

        $server = CustomerRemoteServer::create([
            'label' => 'Test Server', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        CustomerInstallation::create([
            'remote_server_id' => $server->id,
            'installer_user_id' => 1,
            'install_mode' => 'MODE_FRESH',
            'status' => 'complete',
            'site_id' => $site->id,
            'customer_company' => $customer->name,
            'customer_email' => 'noreply@example.com',
            'customer_domain' => $site->domain,
        ]);

        return compact('site', 'server', 'customer');
    }

    private function makeUser(): User
    {
        return User::create([
            'mobile' => '0912' . random_int(1000000, 9999999),
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_active' => true,
        ]);
    }

    private function makeModule(string $type = Module::TYPE_CORE, array $overrides = []): Module
    {
        return Module::updateOrCreate(
            ['slug' => self::SLUG],
            array_merge(['title' => 'ماژول فرم', 'is_active' => true, 'version' => '1.0.0', 'type' => $type], $overrides)
        );
    }

    private function makeReleaseWithPackage(Module $module, string $version = '1.0.0'): ModuleRelease
    {
        return ModuleRelease::create([
            'module_id' => $module->id,
            'version' => $version,
            'release_type' => 'minor',
            'is_active' => true,
            'package_sha256' => str_repeat('a', 64),
            'package_path' => '/tmp/forms-package.zip',
            'package_size_bytes' => 1024,
        ]);
    }

    private function siteModuleRow(int $siteId, int $moduleId): ?object
    {
        return DB::table('site_modules')->where('site_id', $siteId)->where('module_id', $moduleId)->first();
    }

    // ─── Scenario 1 — successful install ───────────────────────────────────

    public function test_install_succeeds_and_creates_a_pending_install_state(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        $user = $this->makeUser();
        $module = $this->makeModule(overrides: ['free_assignable' => true]);
        $this->makeReleaseWithPackage($module);

        $result = $this->service()->deployModule($site, $module, ModuleDeploymentRequestService::MODE_INSTALL, $user);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['installation']);
        $this->assertSame('MODE_MODULE_INSTALL', $result['installation']->install_mode);

        $row = $this->siteModuleRow($site->id, $module->id);
        $this->assertTrue((bool) $row->is_active);
        $this->assertSame('pending_install', $row->deployment_status);
        $this->assertSame($result['installation']->id, $row->last_installation_id);

        Bus::assertDispatched(CustomerInstallationPipelineJob::class, fn ($job) => $job->installationId === $result['installation']->id);
    }

    // ─── Scenario 2 — successful upgrade ───────────────────────────────────

    public function test_upgrade_succeeds_for_an_already_installed_module(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        $user = $this->makeUser();
        $module = $this->makeModule(overrides: ['free_assignable' => true]);
        $this->makeReleaseWithPackage($module, '1.1.0');

        DB::table('site_modules')->insert([
            'site_id' => $site->id,
            'module_id' => $module->id,
            'is_active' => true,
            'deployment_status' => 'installed',
            'installed_version' => '1.0.0',
            'activated_at' => now()->subDays(10),
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $result = $this->service()->upgradeModule($site, $module, $user, ['metadata' => ['requested_reason' => 'security patch']]);

        $this->assertTrue($result['success']);
        $this->assertSame('MODE_MODULE_UPDATE', $result['installation']->install_mode);
        $this->assertSame('security patch', $result['installation']->metadata['requested_reason']);
        $this->assertSame('1.1.0', $result['installation']->metadata['target_version']);

        Bus::assertDispatched(CustomerInstallationPipelineJob::class);
    }

    public function test_upgrade_rejected_when_module_was_never_installed(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        $module = $this->makeModule(overrides: ['free_assignable' => true]);
        $this->makeReleaseWithPackage($module);

        $result = $this->service()->upgradeModule($site, $module);

        $this->assertFalse($result['success']);
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
    }

    // ─── Scenario 3 — internal module rejected ─────────────────────────────

    public function test_internal_module_is_rejected(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        $module = $this->makeModule(Module::TYPE_INTERNAL);

        $result = $this->service()->deployModule($site, $module);

        $this->assertFalse($result['success']);
        $this->assertDatabaseCount('customer_installations', 1);
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
        $this->assertNull($this->siteModuleRow($site->id, $module->id));
    }

    // ─── Scenario 4 — missing license rejected ─────────────────────────────

    public function test_license_only_module_without_valid_license_is_rejected(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        // Must be TYPE_PAID, not TYPE_CORE — ModuleLicenseService::hasValidLicense()
        // short-circuits to true for TYPE_CORE regardless of license_required.
        $module = $this->makeModule(Module::TYPE_PAID, ['license_required' => true, 'free_assignable' => false]);
        $this->makeReleaseWithPackage($module);

        $result = $this->service()->deployModule($site, $module);

        $this->assertFalse($result['success']);
        $this->assertArrayNotHasKey('installation', $result);
        $this->assertDatabaseCount('customer_installations', 1);
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
        $this->assertNull($this->siteModuleRow($site->id, $module->id));
    }

    public function test_license_only_module_with_valid_license_succeeds(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        $module = $this->makeModule(Module::TYPE_PAID, ['license_required' => true, 'free_assignable' => false]);
        $this->makeReleaseWithPackage($module);

        ModuleLicense::create([
            'site_id' => $site->id,
            'module_id' => $module->id,
            'license_type' => ModuleLicense::TYPE_ONE_TIME,
            'purchased_at' => now(),
            'status' => ModuleLicense::STATUS_ACTIVE,
        ]);

        $result = $this->service()->deployModule($site, $module);

        $this->assertTrue($result['success']);
        Bus::assertDispatched(CustomerInstallationPipelineJob::class);
    }

    // ─── Scenario 5 — duplicate running installation rejected ─────────────

    public function test_duplicate_running_installation_is_rejected(): void
    {
        Bus::fake();
        ['site' => $site, 'server' => $server] = $this->makeSite();
        $module = $this->makeModule(overrides: ['free_assignable' => true]);
        $this->makeReleaseWithPackage($module);

        CustomerInstallation::create([
            'remote_server_id' => $server->id,
            'installer_user_id' => 1,
            'install_mode' => 'MODE_MODULE_INSTALL',
            'status' => 'running',
            'site_id' => $site->id,
            'customer_company' => 'مشتری تست',
            'customer_email' => 'noreply@example.com',
            'customer_domain' => $site->domain,
        ]);

        $result = $this->service()->deployModule($site, $module);

        $this->assertFalse($result['success']);
        $this->assertDatabaseCount('customer_installations', 2);
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
    }

    public function test_dispatch_rejected_when_lock_already_held(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        $module = $this->makeModule(overrides: ['free_assignable' => true]);
        $this->makeReleaseWithPackage($module);

        $lock = \Illuminate\Support\Facades\Cache::lock(ModuleDeploymentRequestService::lockKey($site->id), 10);
        $this->assertTrue($lock->get());

        try {
            $result = $this->service()->deployModule($site, $module);

            $this->assertFalse($result['success']);
            Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
        } finally {
            $lock->release();
        }
    }

    // ─── Scenario 6 — failed dispatch leaves no half-finished state ───────

    public function test_failed_dispatch_cleans_up_installation_and_marks_deployment_failed(): void
    {
        ['site' => $site] = $this->makeSite();
        $module = $this->makeModule(overrides: ['free_assignable' => true]);
        $this->makeReleaseWithPackage($module);

        Bus::shouldReceive('dispatch')->andThrow(new \RuntimeException('queue unreachable'));

        $result = $this->service()->deployModule($site, $module);

        $this->assertFalse($result['success']);
        $this->assertDatabaseCount('customer_installations', 1); // only the MODE_FRESH fixture row

        $row = $this->siteModuleRow($site->id, $module->id);
        $this->assertSame('failed', $row->deployment_status);
    }

    public function test_no_package_available_creates_no_installation(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        $module = $this->makeModule(overrides: ['free_assignable' => true]);

        $result = $this->service()->deployModule($site, $module);

        $this->assertFalse($result['success']);
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
    }

    // ─── Scenario 7 — payment activation uses the correct path ────────────

    public function test_payment_activation_routes_through_the_shared_deployment_service(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        $module = $this->makeModule(overrides: [
            'free_assignable' => false,
            'license_required' => false,
            'price_irr' => 100000,
            'marketplace_visible' => true,
        ]);
        $this->makeReleaseWithPackage($module);

        $result = app(ModuleAssignmentService::class)->activatePaidPurchase($site, $module->id);

        $this->assertTrue($result['success']);
        $this->assertSame('MODE_MODULE_INSTALL', $result['installation']->install_mode);
        Bus::assertDispatched(CustomerInstallationPipelineJob::class, fn ($job) => $job->installationId === $result['installation']->id);

        $row = $this->siteModuleRow($site->id, $module->id);
        $this->assertTrue((bool) $row->is_active);
    }

    // ─── Scenario 8 — admin UI (manual assign) uses the correct path ──────

    public function test_admin_manual_assign_routes_through_the_shared_deployment_service(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        $user = $this->makeUser();
        $module = $this->makeModule(overrides: ['free_assignable' => true]);
        $this->makeReleaseWithPackage($module);

        $result = app(ModuleAssignmentService::class)->enableForSite($site, $module->id, $user->id);

        $this->assertTrue($result['success']);
        Bus::assertDispatched(CustomerInstallationPipelineJob::class, fn ($job) => $job->installationId === $result['installation']->id);
        $this->assertSame($user->id, $result['installation']->installer_user_id);
    }

    public function test_module_already_installed_dispatches_via_update_mode_not_install(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        $module = $this->makeModule(overrides: ['free_assignable' => true]);
        $this->makeReleaseWithPackage($module, '1.1.0');

        DB::table('site_modules')->insert([
            'site_id' => $site->id,
            'module_id' => $module->id,
            'is_active' => true,
            'deployment_status' => 'installed',
            'installed_version' => '1.0.0',
            'activated_at' => now()->subDays(10),
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $result = $this->service()->deployModule($site, $module, ModuleDeploymentRequestService::MODE_INSTALL);

        $this->assertTrue($result['success']);
        $this->assertSame('MODE_MODULE_UPDATE', $result['installation']->install_mode);
    }
}
