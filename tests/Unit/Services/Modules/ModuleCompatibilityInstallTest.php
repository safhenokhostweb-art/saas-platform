<?php

namespace Tests\Unit\Services\Modules;

use App\Jobs\CustomerInstallationPipelineJob;
use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Site;
use App\Services\Modules\ModuleDeploymentRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\CoreUtils\Services\PlatformCompatibilityService;
use Tests\TestCase;

/**
 * Phase 17-F — Part 8: proves PlatformCompatibilityService::check() now
 * gates every ModuleDeploymentRequestService mode (install/upgrade), not
 * just the Upgrade UI's own eligibility list (ModuleUpdateEligibilityService)
 * — the Part 1 audit's central finding.
 */
class ModuleCompatibilityInstallTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'forms';

    private function makeSite(): array
    {
        $customer = Customer::create(['name' => 'مشتری تست', 'mobile' => '0912' . random_int(1000000, 9999999)]);

        $site = Site::create([
            'customer_id' => $customer->id, 'title' => 'سایت تست',
            'domain' => 'test-' . uniqid('', true) . '.example.com', 'is_active' => true,
            'support_expires_at' => now()->addYear(),
        ]);

        $server = CustomerRemoteServer::create([
            'label' => 'Test Server', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);

        CustomerInstallation::create([
            'remote_server_id' => $server->id, 'installer_user_id' => 1, 'install_mode' => 'MODE_FRESH',
            'status' => 'complete', 'site_id' => $site->id, 'customer_company' => $customer->name,
            'customer_email' => 'noreply@example.com', 'customer_domain' => $site->domain,
        ]);

        return compact('site', 'customer');
    }

    private function makeModuleWithPackage(string $version = '1.0.0'): Module
    {
        $module = Module::updateOrCreate(
            ['slug' => self::SLUG],
            ['title' => 'ماژول فرم', 'is_active' => true, 'version' => $version, 'type' => Module::TYPE_CORE, 'free_assignable' => true]
        );

        ModuleRelease::create([
            'module_id' => $module->id, 'version' => $version, 'release_type' => 'minor', 'is_active' => true,
            'package_sha256' => str_repeat('a', 64), 'package_path' => '/tmp/forms-package.zip', 'package_size_bytes' => 1024,
        ]);

        return $module;
    }

    private function mockIncompatible(): void
    {
        $compat = $this->createMock(PlatformCompatibilityService::class);
        $compat->method('check')->willReturn(['passed' => false, 'errors' => ['PHP 9.0 is required, running 8.4'], 'warnings' => []]);
        $this->app->instance(PlatformCompatibilityService::class, $compat);
    }

    // ─── Scenario 9 — install rejected when incompatible ──────────────────

    public function test_install_is_rejected_when_platform_incompatible(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        $module = $this->makeModuleWithPackage();
        $this->mockIncompatible();

        $result = app(ModuleDeploymentRequestService::class)->deployModule($site, $module);

        $this->assertFalse($result['success']);
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);

        // No half-mutated state: entitlement/deployment_status untouched.
        $row = DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $module->id)->first();
        $this->assertNull($row);

        // Only the MODE_FRESH fixture row — no new CustomerInstallation.
        $this->assertDatabaseCount('customer_installations', 1);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'module.deployment_incompatible',
            'subject_type' => Site::class,
            'subject_id' => $site->id,
        ]);
    }

    public function test_upgrade_is_rejected_when_platform_incompatible(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        $module = $this->makeModuleWithPackage('1.0.0');
        DB::table('site_modules')->insert([
            'site_id' => $site->id, 'module_id' => $module->id, 'is_active' => true,
            'deployment_status' => 'installed', 'installed_version' => '0.9.0',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->mockIncompatible();

        $result = app(ModuleDeploymentRequestService::class)->upgradeModule($site, $module);

        $this->assertFalse($result['success']);
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);

        $row = DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $module->id)->first();
        $this->assertSame('0.9.0', $row->installed_version); // untouched

        $this->assertDatabaseHas('activity_logs', ['action' => 'module.deployment_incompatible']);
    }

    // ─── Scenario 10 — compatible install/upgrade still succeeds ──────────

    public function test_install_still_succeeds_when_platform_compatible(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        $module = $this->makeModuleWithPackage();
        // Real PlatformCompatibilityService (not mocked) — forms/module.json
        // declares php:^8.1, laravel:^10.0|^11.0, saas-platform:>=1.0.0, all
        // satisfied by this test environment.

        $result = app(ModuleDeploymentRequestService::class)->deployModule($site, $module);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        Bus::assertDispatched(CustomerInstallationPipelineJob::class);
    }

    public function test_upgrade_still_succeeds_when_platform_compatible(): void
    {
        Bus::fake();
        ['site' => $site] = $this->makeSite();
        $module = $this->makeModuleWithPackage('1.1.0');
        DB::table('site_modules')->insert([
            'site_id' => $site->id, 'module_id' => $module->id, 'is_active' => true,
            'deployment_status' => 'installed', 'installed_version' => '1.0.0',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = app(ModuleDeploymentRequestService::class)->upgradeModule($site, $module);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertSame('MODE_MODULE_UPDATE', $result['installation']->install_mode);
        Bus::assertDispatched(CustomerInstallationPipelineJob::class);
    }
}
