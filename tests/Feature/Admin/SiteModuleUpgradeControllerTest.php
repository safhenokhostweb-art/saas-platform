<?php

namespace Tests\Feature\Admin;

use App\Jobs\CustomerInstallationPipelineJob;
use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 13-K — SiteModuleUpgradeController scenarios.
 *
 * Uses the real "forms" package (packages/saas-platform/modules/forms) so
 * PlatformCompatibilityService (12-K) has a real module.json to evaluate,
 * matching the pattern already used throughout the 12-F..13-J test suite.
 */
class SiteModuleUpgradeControllerTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'forms';

    private function admin(): User
    {
        return User::create([
            'mobile' => '09121234567',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_active' => true,
        ]);
    }

    private function makeSiteWithModule(string $installed, string $available, bool $withPackage = true): array
    {
        // The customers table has no email column at all — the controller's
        // `$customer?->email ?? 'noreply@example.com'` fallback is real
        // production behavior for this schema, not a defensive-only branch.
        $customer = Customer::create([
            'name' => 'مشتری تست',
            'mobile' => '0912' . random_int(1000000, 9999999),
        ]);

        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت تست',
            'domain' => 'test-' . uniqid('', true) . '.example.com',
            'is_active' => true,
            'support_expires_at' => now()->addYear(),
        ]);

        $module = Module::updateOrCreate(
            ['slug' => self::SLUG],
            ['title' => 'ماژول فرم', 'is_active' => true, 'version' => $installed, 'type' => Module::TYPE_CORE]
        );

        $site->modules()->attach($module->id, ['is_active' => true, 'installed_version' => $installed]);

        $release = ModuleRelease::create([
            'module_id' => $module->id,
            'version' => $available,
            'release_type' => 'minor',
            'is_active' => true,
            'package_sha256' => $withPackage ? str_repeat('a', 64) : null,
            'package_path' => $withPackage ? '/tmp/forms-package.zip' : null,
            'package_size_bytes' => $withPackage ? 1024 : null,
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

        return compact('site', 'module', 'release', 'server', 'customer');
    }

    public function test_index_shows_eligible_upgrade_with_package(): void
    {
        $this->actingAs($this->admin());
        ['site' => $site] = $this->makeSiteWithModule('1.0.0', '1.1.0');

        $response = $this->get(route('admin.sites.module-upgrades.index', $site));

        $response->assertOk();
        $response->assertSee('1.0.0');
        $response->assertSee('1.1.0');
        $response->assertSee(route('admin.sites.module-upgrades.confirm', [$site, self::SLUG]), false);
    }

    public function test_index_shows_no_package_notice_when_package_not_built(): void
    {
        $this->actingAs($this->admin());
        ['site' => $site] = $this->makeSiteWithModule('1.0.0', '1.1.0', withPackage: false);

        $response = $this->get(route('admin.sites.module-upgrades.index', $site));

        $response->assertOk();
        $response->assertSee('بستهٔ نصب هنوز ساخته نشده است');
        $response->assertDontSee(route('admin.sites.module-upgrades.confirm', [$site, self::SLUG]), false);
    }

    public function test_confirm_page_shows_upgrade_details(): void
    {
        $this->actingAs($this->admin());
        ['site' => $site] = $this->makeSiteWithModule('1.0.0', '1.1.0');

        $response = $this->get(route('admin.sites.module-upgrades.confirm', [$site, self::SLUG]));

        $response->assertOk();
        $response->assertSee('1.1.0');
        $response->assertSee('reason', false);
        $response->assertSee('confirmed_upgrade', false);
    }

    public function test_confirm_redirects_when_no_package_registered(): void
    {
        $this->actingAs($this->admin());
        ['site' => $site] = $this->makeSiteWithModule('1.0.0', '1.1.0', withPackage: false);

        $response = $this->get(route('admin.sites.module-upgrades.confirm', [$site, self::SLUG]));

        $response->assertRedirect(route('admin.sites.module-upgrades.index', $site));
    }

    public function test_store_without_reason_fails_validation_and_creates_nothing(): void
    {
        $this->actingAs($this->admin());
        ['site' => $site] = $this->makeSiteWithModule('1.0.0', '1.1.0');

        $response = $this->post(route('admin.sites.module-upgrades.store', [$site, self::SLUG]), [
            'confirmed_upgrade' => '1',
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('customer_installations', 1); // only the fixture's MODE_FRESH row
    }

    public function test_store_without_confirmation_checkbox_fails_validation(): void
    {
        $this->actingAs($this->admin());
        ['site' => $site] = $this->makeSiteWithModule('1.0.0', '1.1.0');

        $response = $this->post(route('admin.sites.module-upgrades.store', [$site, self::SLUG]), [
            'reason' => 'Security patch needed',
        ]);

        $response->assertSessionHasErrors('confirmed_upgrade');
        $this->assertDatabaseCount('customer_installations', 1);
    }

    public function test_store_with_valid_confirmation_creates_installation_and_dispatches_job(): void
    {
        Bus::fake();
        $this->actingAs($this->admin());
        ['site' => $site, 'module' => $module, 'release' => $release] = $this->makeSiteWithModule('1.0.0', '1.1.0');

        $response = $this->post(route('admin.sites.module-upgrades.store', [$site, self::SLUG]), [
            'reason' => 'Security patch needed for this module',
            'confirmed_upgrade' => '1',
        ]);

        $upgrade = CustomerInstallation::where('install_mode', 'MODE_MODULE_UPDATE')->first();

        $this->assertNotNull($upgrade);
        $this->assertSame('pending', $upgrade->status);
        $this->assertSame($site->id, $upgrade->site_id);
        $this->assertSame(self::SLUG, $upgrade->metadata['module_slug']);
        $this->assertSame($release->id, $upgrade->metadata['module_release_id']);
        $this->assertSame('1.1.0', $upgrade->metadata['target_version']);
        $this->assertSame('Security patch needed for this module', $upgrade->metadata['requested_reason']);

        Bus::assertDispatched(CustomerInstallationPipelineJob::class, fn ($job) => $job->installationId === $upgrade->id);

        $response->assertRedirect(route('admin.infrastructure.customer-deployment.installations.show', $upgrade));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'module.upgrade_requested',
            'subject_type' => Site::class,
            'subject_id' => $site->id,
        ]);
    }

    public function test_store_fails_without_known_remote_server(): void
    {
        Bus::fake();
        $this->actingAs($this->admin());
        ['site' => $site] = $this->makeSiteWithModule('1.0.0', '1.1.0');

        // Remove the completed installation the fixture created, so no remote
        // server can be resolved for this site.
        CustomerInstallation::where('site_id', $site->id)->delete();

        $response = $this->post(route('admin.sites.module-upgrades.store', [$site, self::SLUG]), [
            'reason' => 'Security patch needed for this module',
            'confirmed_upgrade' => '1',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('customer_installations', 0);
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
    }

    public function test_store_fails_when_package_not_registered(): void
    {
        Bus::fake();
        $this->actingAs($this->admin());
        ['site' => $site] = $this->makeSiteWithModule('1.0.0', '1.1.0', withPackage: false);

        $response = $this->post(route('admin.sites.module-upgrades.store', [$site, self::SLUG]), [
            'reason' => 'Security patch needed for this module',
            'confirmed_upgrade' => '1',
        ]);

        $response->assertRedirect(route('admin.sites.module-upgrades.index', $site));
        $this->assertDatabaseCount('customer_installations', 1); // only the fixture's MODE_FRESH row
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
    }
}
