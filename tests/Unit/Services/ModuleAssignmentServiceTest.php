<?php

namespace Tests\Unit\Services;

use App\Jobs\CustomerInstallationPipelineJob;
use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Site;
use App\Services\ModuleAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 17-D — ModuleAssignmentService scenarios: enableForSite()/
 * disableForSite() as array-returning, guard-preserving entry points, plus
 * activatePaidPurchase() and the shared bulk-diff helper.
 */
class ModuleAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'forms';

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function service(): ModuleAssignmentService
    {
        return app(ModuleAssignmentService::class);
    }

    private function makeSite(): Site
    {
        $customer = Customer::create(['name' => 'مشتری تست', 'mobile' => '0912' . random_int(1000000, 9999999)]);
        $site = Site::create([
            'customer_id' => $customer->id, 'title' => 'سایت تست',
            'domain' => 'test-' . uniqid('', true) . '.example.com', 'is_active' => true,
        ]);
        $server = CustomerRemoteServer::create([
            'label' => 'Test Server', 'type' => 'fake', 'host' => 'localhost',
            'port' => 22, 'username' => 'deploy', 'auth_method' => 'key',
        ]);
        CustomerInstallation::create([
            'remote_server_id' => $server->id, 'installer_user_id' => 1,
            'install_mode' => 'MODE_FRESH', 'status' => 'complete', 'site_id' => $site->id,
            'customer_company' => $customer->name, 'customer_email' => 'noreply@example.com',
            'customer_domain' => $site->domain,
        ]);
        return $site;
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
            'module_id' => $module->id, 'version' => $version, 'release_type' => 'minor',
            'is_active' => true, 'package_sha256' => str_repeat('a', 64),
            'package_path' => '/tmp/forms.zip', 'package_size_bytes' => 1024,
        ]);
    }

    public function test_enable_for_site_returns_array_and_syncs_entitlement_ledger(): void
    {
        Bus::fake();
        $site = $this->makeSite();
        $module = $this->makeModule(overrides: ['free_assignable' => true]);
        $this->makeReleaseWithPackage($module);

        $result = $this->service()->enableForSite($site, $module->id, 1);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('module_entitlements', [
            'site_id' => $site->id, 'module_id' => $module->id, 'status' => 'active',
        ]);
    }

    public function test_disable_for_site_preserves_deployment_state(): void
    {
        $site = $this->makeSite();
        $module = $this->makeModule(overrides: ['free_assignable' => true]);
        $installation = CustomerInstallation::where('site_id', $site->id)->first();
        DB::table('site_modules')->insert([
            'site_id' => $site->id, 'module_id' => $module->id,
            'is_active' => true, 'deployment_status' => 'installed', 'installed_version' => '1.0.0',
            'last_installation_id' => $installation->id,
            'activated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $result = $this->service()->disableForSite($site, $module->id);

        $this->assertTrue($result['success']);
        $row = DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $module->id)->first();
        $this->assertFalse((bool) $row->is_active);
        $this->assertSame('installed', $row->deployment_status);
        $this->assertSame('1.0.0', $row->installed_version);
        $this->assertSame($installation->id, $row->last_installation_id);
    }

    public function test_disable_for_site_is_rejected_while_an_installation_is_running_for_that_module(): void
    {
        $site = $this->makeSite();
        $module = $this->makeModule(overrides: ['free_assignable' => true]);
        DB::table('site_modules')->insert([
            'site_id' => $site->id, 'module_id' => $module->id, 'is_active' => true,
            'deployment_status' => 'pending_install', 'created_at' => now(), 'updated_at' => now(),
        ]);
        CustomerInstallation::create([
            'remote_server_id' => CustomerRemoteServer::first()->id, 'installer_user_id' => 1,
            'install_mode' => 'MODE_MODULE_INSTALL', 'status' => 'running', 'site_id' => $site->id,
            'customer_company' => 'x', 'customer_email' => 'x@example.com', 'customer_domain' => $site->domain,
            'metadata' => ['module_slug' => $module->slug],
        ]);

        $result = $this->service()->disableForSite($site, $module->id);

        $this->assertFalse($result['success']);
        $row = DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $module->id)->first();
        $this->assertTrue((bool) $row->is_active, 'disable must be rejected, not silently applied');
    }

    public function test_activate_paid_purchase_guarantees_entitlement_even_when_dispatch_fails(): void
    {
        Bus::fake();
        $site = $this->makeSite();
        // No remote server resolvable -> dispatch will fail.
        CustomerInstallation::where('site_id', $site->id)->delete();
        $module = $this->makeModule(Module::TYPE_PAID, [
            'free_assignable' => false, 'license_required' => false,
            'price_irr' => 100000, 'marketplace_visible' => true,
        ]);
        $this->makeReleaseWithPackage($module);

        $result = $this->service()->activatePaidPurchase($site, $module->id);

        $this->assertFalse($result['success']);
        $row = DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $module->id)->first();
        $this->assertNotNull($row, 'a paid purchase must always grant an entitlement row, even on dispatch failure');
        $this->assertTrue((bool) $row->is_active, 'payment already happened — access must not depend on infra');
        $this->assertSame('failed', $row->deployment_status);
    }

    public function test_activate_paid_purchase_rejects_non_paid_module(): void
    {
        $site = $this->makeSite();
        $module = $this->makeModule(overrides: ['free_assignable' => true]); // not paid marketplace

        $result = $this->service()->activatePaidPurchase($site, $module->id);

        $this->assertFalse($result['success']);
        $this->assertNull(DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $module->id)->first());
    }

    public function test_sync_manual_assignments_computes_enable_disable_unchanged(): void
    {
        Bus::fake();
        $site = $this->makeSite();
        $moduleA = Module::create(['slug' => 'mod-a', 'title' => 'A', 'is_active' => true, 'type' => Module::TYPE_CORE, 'free_assignable' => true, 'version' => '1.0.0']);
        $moduleB = Module::create(['slug' => 'mod-b', 'title' => 'B', 'is_active' => true, 'type' => Module::TYPE_CORE, 'free_assignable' => true, 'version' => '1.0.0']);
        $moduleC = Module::create(['slug' => 'mod-c', 'title' => 'C', 'is_active' => true, 'type' => Module::TYPE_CORE, 'free_assignable' => true, 'version' => '1.0.0']);
        foreach ([$moduleA, $moduleB, $moduleC] as $m) {
            $this->makeReleaseWithPackage($m);
        }

        // B already active; A and C not.
        DB::table('site_modules')->insert([
            'site_id' => $site->id, 'module_id' => $moduleB->id, 'is_active' => true,
            'deployment_status' => 'installed', 'installed_version' => '1.0.0',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Request: A becomes active (new), B stays active (unchanged), C stays inactive (unchanged).
        $results = $this->service()->syncManualAssignmentsForSite(
            $site,
            [$moduleA->id, $moduleB->id, $moduleC->id],
            [$moduleA->id, $moduleB->id],
            1
        );

        $byModule = collect($results)->keyBy('module_id');
        $this->assertSame('enabled', $byModule[$moduleA->id]['result']);
        $this->assertSame('unchanged', $byModule[$moduleB->id]['result']);
        $this->assertSame('unchanged', $byModule[$moduleC->id]['result']);

        // Unchanged modules must never trigger a dispatch.
        Bus::assertDispatchedTimes(CustomerInstallationPipelineJob::class, 1);
    }

    public function test_enable_for_site_without_release_package_activates_entitlement_directly(): void
    {
        $site = $this->makeSite();
        $module = $this->makeModule(overrides: ['free_assignable' => true]);

        $result = $this->service()->enableForSite($site, $module->id, 1);

        $this->assertTrue($result['success']);
        $row = DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $module->id)->first();
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->is_active);
        $this->assertSame('installed', $row->deployment_status);
        $this->assertDatabaseHas('module_entitlements', [
            'site_id' => $site->id, 'module_id' => $module->id, 'status' => 'active',
        ]);
    }
}
