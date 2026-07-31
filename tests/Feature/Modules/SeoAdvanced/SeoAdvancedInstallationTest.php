<?php

namespace Tests\Feature\Modules\SeoAdvanced;

use App\Jobs\CustomerInstallationPipelineJob;
use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerRemoteServer;
use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\Site;
use App\Models\User;
use App\Services\ModuleAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 18 — Part 9/10: proves the generic, already-built Module Ecosystem
 * (Phases 17-C..17-I) works end-to-end for this real commercial module
 * with zero platform code changes — only the catalog seed migration
 * (2026_07_29_110000_seed_seo_advanced_marketplace_module) was needed.
 */
class SeoAdvancedInstallationTest extends TestCase
{
    use RefreshDatabase;

    private function makeSite(): Site
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

        return $site;
    }

    /** The catalog row is seeded by migration, already present — this just gives it a real, published, deployable release. */
    private function publishRelease(): Module
    {
        $module = Module::where('slug', 'seo-advanced')->firstOrFail();

        ModuleRelease::create([
            'module_id' => $module->id, 'version' => '1.0.0', 'release_type' => 'minor',
            'is_active' => true, 'status' => ModuleRelease::STATUS_PUBLISHED,
            'package_sha256' => str_repeat('a', 64), 'package_path' => '/tmp/seo-advanced.zip', 'package_size_bytes' => 1024,
        ]);

        return $module;
    }

    // ─── Catalog seed sanity ────────────────────────────────────────────────

    public function test_seo_advanced_is_seeded_as_a_paid_marketplace_module_distinct_from_legacy_placeholder(): void
    {
        $module = Module::where('slug', 'seo-advanced')->firstOrFail();

        $this->assertTrue($module->isPaidMarketplace());
        $this->assertDatabaseHas('products', ['module_id' => $module->id, 'type' => 'module_addon', 'is_active' => true]);

        // The pre-existing roadmap placeholder (underscore slug) must be
        // completely untouched — a different row, never merged/renamed.
        $this->assertDatabaseHas('modules', ['slug' => 'seo_advanced']);
        $this->assertNotSame(
            Module::where('slug', 'seo_advanced')->value('id'),
            $module->id
        );
    }

    // ─── Scenario: Installation via the generic Deployment flow ───────────

    public function test_activate_paid_purchase_deploys_seo_advanced_through_the_generic_pipeline(): void
    {
        Bus::fake();
        $site = $this->makeSite();
        $module = $this->publishRelease();

        $result = app(ModuleAssignmentService::class)->activatePaidPurchase($site, $module->id);

        $this->assertTrue($result['success'], $result['error'] ?? '');
        $this->assertSame('MODE_MODULE_INSTALL', $result['installation']->install_mode);
        Bus::assertDispatched(CustomerInstallationPipelineJob::class);
    }

    // ─── Scenario: License restriction — paid marketplace, purchase-only ──

    public function test_manual_enable_is_rejected_seo_advanced_is_purchase_only(): void
    {
        $site = $this->makeSite();
        $module = $this->publishRelease();

        $this->assertFalse(app(ModuleAssignmentService::class)->canEnableForSite($site, $module->id));

        $result = app(ModuleAssignmentService::class)->enableForSite($site, $module->id);
        $this->assertFalse($result['success']);
    }

    public function test_install_is_rejected_when_no_published_release_exists_yet(): void
    {
        Bus::fake();
        $site = $this->makeSite();
        $module = Module::where('slug', 'seo-advanced')->firstOrFail();
        // No ModuleRelease created — module exists in the catalog but was
        // never released, matching a real "just created, not yet released"
        // commercial module.

        $result = app(ModuleAssignmentService::class)->activatePaidPurchase($site, $module->id);

        $this->assertFalse($result['success']);
        Bus::assertNotDispatched(CustomerInstallationPipelineJob::class);
    }

    // ─── Scenario: Permission — admin routes require auth+admin ───────────

    public function test_admin_dashboard_requires_authentication(): void
    {
        $response = $this->get(route('seo-advanced.admin.dashboard', 1));

        $response->assertRedirect(route('login'));
    }

    public function test_admin_dashboard_reachable_for_an_authenticated_admin(): void
    {
        $admin = User::create([
            'mobile' => '0912' . random_int(1000000, 9999999), 'password' => Hash::make('password'),
            'is_admin' => true, 'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('seo-advanced.admin.dashboard', 1));

        $response->assertOk();
    }

    // ─── Customer Runtime — public routes ──────────────────────────────────

    public function test_public_sitemap_route_returns_xml(): void
    {
        $response = $this->get(route('seo-advanced.runtime.sitemap', 1));

        $response->assertOk();
        $this->assertStringStartsWith('application/xml', $response->headers->get('Content-Type'));
    }

    public function test_public_robots_route_returns_plain_text(): void
    {
        $response = $this->get(route('seo-advanced.runtime.robots', 1));

        $response->assertOk();
        $response->assertSee('User-agent: *');
    }
}
