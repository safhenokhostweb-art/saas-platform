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
use App\Services\ModuleLicenseService;
use App\Services\Modules\ModuleInstallationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 17-C — Part 2/8: ModuleInstallationDispatcher scenarios. Mirrors
 * SiteModuleUpgradeControllerTest's fixture shape (real "forms" package,
 * a MODE_FRESH-complete installation to resolve the remote server from).
 */
class ModuleInstallationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'forms';

    private function dispatcher(): ModuleInstallationDispatcher
    {
        return new ModuleInstallationDispatcher(app(ModuleLicenseService::class));
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

    // ─── Scenario 1 — free module enable ───────────────────────────────────

    public function test_enabling_a_free_assignable_module_creates_a_pending_install_state(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        $module = $this->makeModule(overrides: ['free_assignable' => true]);
        $this->makeReleaseWithPackage($module);

        $result = $this->dispatcher()->dispatch($site, $module, 1);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['installation']);
        $this->assertSame('MODE_MODULE_INSTALL', $result['installation']->install_mode);

        $row = $this->siteModuleRow($site->id, $module->id);
        $this->assertTrue((bool) $row->is_active);
        $this->assertSame('pending_install', $row->deployment_status);
        $this->assertSame($result['installation']->id, $row->last_installation_id);

        Bus::assertDispatched(CustomerInstallationPipelineJob::class, fn ($job) => $job->installationId === $result['installation']->id);
    }

    // ─── Scenario 2 — invalid license ──────────────────────────────────────

    public function test_license_only_module_without_valid_license_creates_no_installation(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        // Must be TYPE_PAID, not TYPE_CORE — ModuleLicenseService::hasValidLicense()
        // short-circuits to true for TYPE_CORE regardless of license_required.
        $module = $this->makeModule(Module::TYPE_PAID, ['license_required' => true, 'free_assignable' => false]);
        $this->makeReleaseWithPackage($module);

        $result = $this->dispatcher()->dispatch($site, $module, 1);

        $this->assertFalse($result['success']);
        $this->assertArrayNotHasKey('installation', $result);
        $this->assertDatabaseCount('customer_installations', 1); // only the MODE_FRESH fixture row
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

        $result = $this->dispatcher()->dispatch($site, $module, 1);

        $this->assertTrue($result['success']);
        Bus::assertDispatched(CustomerInstallationPipelineJob::class);
    }

    // ─── Scenario 3 — internal module blocked ──────────────────────────────

    public function test_internal_module_is_blocked_and_creates_no_installation(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        $module = $this->makeModule(Module::TYPE_INTERNAL);

        $result = $this->dispatcher()->dispatch($site, $module, 1);

        $this->assertFalse($result['success']);
        $this->assertDatabaseCount('customer_installations', 1);
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
        $this->assertNull($this->siteModuleRow($site->id, $module->id));
    }

    // ─── Scenario 6 — existing installed module uses the Update path ──────

    public function test_a_module_already_installed_dispatches_via_update_mode_not_install(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        $module = $this->makeModule(overrides: ['free_assignable' => true]);
        $this->makeReleaseWithPackage($module, '1.1.0');

        // Simulate an already-installed module (has a real installed_version).
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

        $result = $this->dispatcher()->dispatch($site, $module, 1);

        $this->assertTrue($result['success']);
        $this->assertSame('MODE_MODULE_UPDATE', $result['installation']->install_mode);
    }

    // ─── Additional guard coverage ──────────────────────────────────────────

    public function test_no_package_available_creates_no_installation(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        $module = $this->makeModule(overrides: ['free_assignable' => true]);
        // No ModuleRelease at all.

        $result = $this->dispatcher()->dispatch($site, $module, 1);

        $this->assertFalse($result['success']);
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
    }

    public function test_paid_marketplace_module_is_rejected(): void
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

        $result = $this->dispatcher()->dispatch($site, $module, 1);

        $this->assertFalse($result['success']);
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
    }

    public function test_dispatch_rejected_when_lock_already_held(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        $module = $this->makeModule(overrides: ['free_assignable' => true]);
        $this->makeReleaseWithPackage($module);

        $lock = \Illuminate\Support\Facades\Cache::lock(ModuleInstallationDispatcher::lockKey($site->id), 10);
        $this->assertTrue($lock->get());

        try {
            $result = $this->dispatcher()->dispatch($site, $module, 1);

            $this->assertFalse($result['success']);
            Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
        } finally {
            $lock->release();
        }
    }
}
