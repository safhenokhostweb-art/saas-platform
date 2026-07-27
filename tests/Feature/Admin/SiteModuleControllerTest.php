<?php

namespace Tests\Feature\Admin;

use App\Jobs\CustomerInstallationPipelineJob;
use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Module;
use App\Models\ModuleLicense;
use App\Models\ModuleRelease;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 17-D — Part 7: real route/controller-level coverage for
 * SiteModuleController, proving toggle()/update() go through
 * ModuleAssignmentService (and therefore ModuleDeploymentRequestService) and
 * never touch site_modules directly.
 */
class SiteModuleControllerTest extends TestCase
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

    private function admin(): User
    {
        return User::create([
            'mobile' => '09' . random_int(100000000, 999999999),
            'password' => 'password',
            'is_admin' => true,
            'is_active' => true,
        ]);
    }

    private function makeSite(): array
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
        return compact('site', 'customer', 'server');
    }

    private function makeFreeModule(): Module
    {
        $module = Module::updateOrCreate(
            ['slug' => self::SLUG],
            ['title' => 'ماژول فرم', 'is_active' => true, 'version' => '1.0.0', 'type' => Module::TYPE_CORE, 'free_assignable' => true]
        );
        ModuleRelease::create([
            'module_id' => $module->id, 'version' => '1.0.0', 'release_type' => 'minor',
            'is_active' => true, 'package_sha256' => str_repeat('a', 64),
            'package_path' => '/tmp/forms.zip', 'package_size_bytes' => 1024,
        ]);
        return $module;
    }

    // ─── Part 7 #1 — toggle enable ─────────────────────────────────────────

    public function test_toggle_enable_dispatches_pipeline_not_direct_pivot_activation(): void
    {
        Bus::fake();
        $this->actingAs($this->admin());
        ['site' => $site] = $this->makeSite();
        $module = $this->makeFreeModule();

        $response = $this->post(route('admin.sites.modules.toggle', $site), ['module_id' => $module->id]);

        $response->assertSessionHas('success');
        $row = DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $module->id)->first();
        $this->assertTrue((bool) $row->is_active);
        $this->assertSame('pending_install', $row->deployment_status);
        Bus::assertDispatched(CustomerInstallationPipelineJob::class);
        $this->assertDatabaseHas('customer_installations', ['site_id' => $site->id, 'install_mode' => 'MODE_MODULE_INSTALL']);
    }

    // ─── Part 7 #2 — toggle disable ─────────────────────────────────────────

    public function test_toggle_disable_preserves_installed_version_and_last_installation_id(): void
    {
        $this->actingAs($this->admin());
        ['site' => $site] = $this->makeSite();
        $module = $this->makeFreeModule();

        $installation = CustomerInstallation::where('site_id', $site->id)->first();
        DB::table('site_modules')->insert([
            'site_id' => $site->id, 'module_id' => $module->id,
            'is_active' => true, 'deployment_status' => 'installed',
            'installed_version' => '1.0.0', 'last_installation_id' => $installation->id,
            'activated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->post(route('admin.sites.modules.toggle', $site), ['module_id' => $module->id]);

        $response->assertSessionHas('success');
        $row = DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $module->id)->first();
        $this->assertFalse((bool) $row->is_active);
        $this->assertSame('installed', $row->deployment_status);
        $this->assertSame('1.0.0', $row->installed_version);
        $this->assertSame($installation->id, $row->last_installation_id);
    }

    // ─── Part 7 #3 — internal module rejected ──────────────────────────────

    public function test_toggle_rejects_internal_module(): void
    {
        Bus::fake();
        $this->actingAs($this->admin());
        ['site' => $site] = $this->makeSite();
        $module = Module::create(['slug' => 'internal-test', 'title' => 'داخلی', 'is_active' => true, 'type' => Module::TYPE_INTERNAL]);

        $response = $this->post(route('admin.sites.modules.toggle', $site), ['module_id' => $module->id]);

        $response->assertSessionHas('error');
        $this->assertNull(DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $module->id)->first());
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
    }

    // ─── Part 7 #4 — no license ─────────────────────────────────────────────

    public function test_toggle_rejects_license_only_module_without_valid_license(): void
    {
        Bus::fake();
        $this->actingAs($this->admin());
        ['site' => $site] = $this->makeSite();
        $module = Module::create([
            'slug' => 'license-test', 'title' => 'لایسنس‌دار', 'is_active' => true,
            'type' => Module::TYPE_PAID, 'license_required' => true, 'free_assignable' => false,
        ]);
        ModuleRelease::create([
            'module_id' => $module->id, 'version' => '1.0.0', 'release_type' => 'minor',
            'is_active' => true, 'package_sha256' => str_repeat('a', 64),
        ]);

        $response = $this->post(route('admin.sites.modules.toggle', $site), ['module_id' => $module->id]);

        $response->assertSessionHas('error');
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
    }

    // ─── Part 7 #6 — dispatch failure leaves no half-finished state ───────

    public function test_toggle_enable_when_no_remote_server_leaves_no_active_state(): void
    {
        Bus::fake();
        $this->actingAs($this->admin());
        ['site' => $site] = $this->makeSite();
        $module = $this->makeFreeModule();
        // Remove the completed installation so no remote server resolves.
        CustomerInstallation::where('site_id', $site->id)->delete();

        $response = $this->post(route('admin.sites.modules.toggle', $site), ['module_id' => $module->id]);

        $response->assertSessionHas('error');
        $row = DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $module->id)->first();
        $this->assertNull($row); // no row created at all — no false "active"
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
    }

    // ─── Part 7 #8 — message says queued, not installed ────────────────────

    public function test_toggle_success_message_says_queued_not_installed(): void
    {
        Bus::fake();
        $this->actingAs($this->admin());
        ['site' => $site] = $this->makeSite();
        $module = $this->makeFreeModule();

        $response = $this->post(route('admin.sites.modules.toggle', $site), ['module_id' => $module->id]);

        $response->assertSessionHas('success');
        $message = session('success');
        $this->assertStringContainsString('صف', $message);
        $this->assertStringNotContainsString('نصب کامل شد', $message);
    }

    // ─── Part 7 #7 — bulk update ────────────────────────────────────────────

    public function test_bulk_update_only_enables_newly_checked_modules(): void
    {
        Bus::fake();
        $this->actingAs($this->admin());
        ['site' => $site] = $this->makeSite();
        $module = $this->makeFreeModule();

        $response = $this->put(route('admin.sites.modules.update', $site), [
            'modules' => [$module->id => '1'],
        ]);

        $response->assertSessionHas('success');
        $row = DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $module->id)->first();
        $this->assertTrue((bool) $row->is_active);
        Bus::assertDispatchedTimes(CustomerInstallationPipelineJob::class, 1);
    }

    public function test_bulk_update_unchecked_module_absent_from_input_is_not_disabled(): void
    {
        Bus::fake();
        $this->actingAs($this->admin());
        ['site' => $site] = $this->makeSite();
        $module = $this->makeFreeModule();
        $installation = CustomerInstallation::where('site_id', $site->id)->first();
        DB::table('site_modules')->insert([
            'site_id' => $site->id, 'module_id' => $module->id,
            'is_active' => true, 'deployment_status' => 'installed', 'installed_version' => '1.0.0',
            'last_installation_id' => $installation->id,
            'activated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Submits an empty modules array — mirrors an unchecked checkbox
        // with no hidden fallback input (nothing submitted at all).
        $response = $this->put(route('admin.sites.modules.update', $site), ['modules' => []]);

        $response->assertSessionHas('success');
        $row = DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $module->id)->first();
        $this->assertTrue((bool) $row->is_active, 'absent-from-input must be treated as unchanged, not disabled');
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
    }

    public function test_bulk_update_explicitly_unchecking_a_module_disables_it(): void
    {
        $this->actingAs($this->admin());
        ['site' => $site] = $this->makeSite();
        $module = $this->makeFreeModule();
        $installation = CustomerInstallation::where('site_id', $site->id)->first();
        DB::table('site_modules')->insert([
            'site_id' => $site->id, 'module_id' => $module->id,
            'is_active' => true, 'deployment_status' => 'installed', 'installed_version' => '1.0.0',
            'last_installation_id' => $installation->id,
            'activated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->put(route('admin.sites.modules.update', $site), [
            'modules' => [$module->id => '0'],
        ]);

        $response->assertSessionHas('success');
        $row = DB::table('site_modules')->where('site_id', $site->id)->where('module_id', $module->id)->first();
        $this->assertFalse((bool) $row->is_active);
        $this->assertSame('installed', $row->deployment_status);
    }
}
