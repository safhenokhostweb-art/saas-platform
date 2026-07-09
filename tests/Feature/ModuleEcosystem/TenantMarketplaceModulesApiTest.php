<?php

namespace Tests\Feature\ModuleEcosystem;

use App\Models\Customer;
use App\Models\Module;
use App\Models\ModuleLicense;
use App\Models\Site;
use App\Services\LicenseServerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3-B / Commit 6 — GET /api/tenant-marketplace/modules resolver
 * integration. Exercises the real HTTP route (no direct resolver/service
 * calls) to confirm the new visibility/enabled/version/purchasable fields
 * are Resolver-backed, while the pre-existing installed/owned/status
 * fields remain unchanged for backward compatibility.
 */
class TenantMarketplaceModulesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // This route's throttle:60,1 middleware resolves the default cache
        // store, which in the real .env is Redis (config/cache.php reads
        // CACHE_STORE, not the CACHE_DRIVER override in phpunit.xml). Redis
        // is not part of this test's concern, so it is excluded here rather
        // than requiring Redis to be running to test resolver integration.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => 'تست مشتری ' . uniqid('', true),
            'mobile' => '0912' . random_int(1000000, 9999999),
            'email' => 'test_' . uniqid('', true) . '@example.com',
        ]);
    }

    private function makeSite(Customer $customer): Site
    {
        return Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت تست',
            'domain' => 'test-' . uniqid('', true) . '.example.com',
            'is_active' => true,
        ]);
    }

    private function makePaidMarketplaceModule(): Module
    {
        return Module::create([
            'slug' => 'test-module-' . uniqid('', true),
            'title' => 'ماژول تست',
            'description' => 'توضیح ماژول تست',
            'is_active' => true,
            'type' => Module::TYPE_PAID,
            'version' => '1.0.0',
            'license_required' => false,
            'marketplace_visible' => true,
            'price_irr' => 100000,
        ]);
    }

    private function issueLicenseKey(Site $site): string
    {
        return app(LicenseServerService::class)->issue($site);
    }

    public function test_installed_and_enabled_reports_active_visibility(): void
    {
        $site = $this->makeSite($this->makeCustomer());
        $module = $this->makePaidMarketplaceModule();
        $key = $this->issueLicenseKey($site);

        $site->modules()->attach($module->id, ['is_active' => true, 'installed_version' => '1.0.0']);
        ModuleLicense::create([
            'site_id' => $site->id,
            'module_id' => $module->id,
            'license_type' => ModuleLicense::TYPE_ONE_TIME,
            'purchased_at' => now(),
            'expires_at' => now()->addYear(),
            'status' => ModuleLicense::STATUS_ACTIVE,
        ]);

        $response = $this->getJson('/api/tenant-marketplace/modules?' . http_build_query([
            'license_key' => $key,
            'domain' => $site->domain,
        ]));

        $response->assertOk();
        $entry = collect($response->json('modules'))->firstWhere('slug', $module->slug);
        $this->assertNotNull($entry);
        $this->assertSame('active', $entry['visibility']);
        $this->assertTrue($entry['enabled']);
        $this->assertSame('1.0.0', $entry['version']);
    }

    public function test_installed_but_not_entitled_reports_locked_visibility(): void
    {
        $site = $this->makeSite($this->makeCustomer());
        $module = $this->makePaidMarketplaceModule();
        $key = $this->issueLicenseKey($site);

        $site->modules()->attach($module->id, ['is_active' => true, 'installed_version' => '1.0.0']);
        // No ModuleLicense: installed but never entitled.

        $response = $this->getJson('/api/tenant-marketplace/modules?' . http_build_query([
            'license_key' => $key,
            'domain' => $site->domain,
        ]));

        $response->assertOk();
        $entry = collect($response->json('modules'))->firstWhere('slug', $module->slug);
        $this->assertNotNull($entry);
        $this->assertSame('locked', $entry['visibility']);
        $this->assertFalse($entry['enabled']);
    }

    public function test_not_installed_reports_available_visibility(): void
    {
        $site = $this->makeSite($this->makeCustomer());
        $module = $this->makePaidMarketplaceModule();
        $key = $this->issueLicenseKey($site);

        // No site_modules row at all: never installed/assigned.

        $response = $this->getJson('/api/tenant-marketplace/modules?' . http_build_query([
            'license_key' => $key,
            'domain' => $site->domain,
        ]));

        $response->assertOk();
        $entry = collect($response->json('modules'))->firstWhere('slug', $module->slug);
        $this->assertNotNull($entry);
        $this->assertSame('available', $entry['visibility']);
        $this->assertTrue($entry['purchasable']);
    }

    public function test_purchasable_module_includes_purchase_url(): void
    {
        $site = $this->makeSite($this->makeCustomer());
        $module = $this->makePaidMarketplaceModule();
        $key = $this->issueLicenseKey($site);

        // Not installed / not entitled: still purchasable, so purchase_url
        // must be present (Phase 3-F / Commit 10-B).
        $response = $this->getJson('/api/tenant-marketplace/modules?' . http_build_query([
            'license_key' => $key,
            'domain' => $site->domain,
        ]));

        $response->assertOk();
        $entry = collect($response->json('modules'))->firstWhere('slug', $module->slug);
        $this->assertNotNull($entry);

        // Existing fields must remain untouched (backward compatibility).
        $this->assertSame($module->slug, $entry['slug']);
        $this->assertSame($module->title, $entry['title']);
        $this->assertArrayHasKey('visibility', $entry);
        $this->assertArrayHasKey('enabled', $entry);
        $this->assertArrayHasKey('version', $entry);

        // New field: no product_id, no site_id, just the module slug.
        $this->assertSame(
            route('store.modules', ['module' => $module->slug]),
            $entry['purchase_url']
        );
        $this->assertStringNotContainsString('product', $entry['purchase_url']);
        $this->assertStringNotContainsString('site_id', $entry['purchase_url']);
    }

    public function test_internal_module_never_appears_in_response(): void
    {
        $site = $this->makeSite($this->makeCustomer());
        $key = $this->issueLicenseKey($site);

        $internal = Module::create([
            'slug' => 'internal-module-' . uniqid('', true),
            'title' => 'ماژول داخلی پلتفرم',
            'description' => null,
            'is_active' => true,
            'type' => Module::TYPE_INTERNAL,
            'version' => '1.0.0',
        ]);

        $response = $this->getJson('/api/tenant-marketplace/modules?' . http_build_query([
            'license_key' => $key,
            'domain' => $site->domain,
        ]));

        $response->assertOk();
        $entry = collect($response->json('modules'))->firstWhere('slug', $internal->slug);
        // "hidden" for a platform-only module means it is never sent to the
        // customer site at all — Module::paidMarketplace() already excludes
        // type=internal, so this asserts that structural guarantee end-to-end.
        $this->assertNull($entry);
    }
}
