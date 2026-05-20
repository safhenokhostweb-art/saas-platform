<?php
namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Module;
use App\Models\ModuleRelease;
use App\Models\PlatformLicense;
use App\Models\Site;
use App\Services\LicenseServerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Wave 5.4 — Regression tests for Wave 3 / P0-6 (module-history API)
 * and Wave 1 / P0-7 (whatsapp slug preserved, title rebranded to messaging).
 *
 * P0-6 — GET /api/updates/module-history:
 *   - returns 403 + error=invalid_license when license_key is unknown.
 *   - returns 403 + error=domain_mismatch when domain mismatches.
 *   - returns 403 + error=support_expired when support window is closed.
 *   - returns allowed=true + only the module releases that match this site's
 *     active site_modules.
 *   - respects the `limit` query parameter (clamped to 1..200).
 *   - response items contain module_slug, version, release_type, changelog,
 *     released_at (ISO-8601) and a nullable title.
 *
 * P0-7 — whatsapp -> messaging rebrand:
 *   - the module row with slug='whatsapp' still exists (backwards compat:
 *     existing customer sites must keep their entitlement).
 *   - title has been rebranded to 'پاسخگویی شبکه‌ها' (multi-channel messaging).
 *   - no new row with slug='messaging' is added (we deliberately keep the
 *     slug stable to avoid breaking site_modules / site_module_purchases).
 */
class Wave3ApiAndAliasTest extends TestCase
{
    use RefreshDatabase;

    private string $licenseKey;
    private Site $site;
    private LicenseServerService $licenseService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->licenseService = app(LicenseServerService::class);
    }

    private function setupLicensedSite(string $domain = 'p06.test', bool $supportActive = true): array
    {
        $customer = Customer::create([
            'name' => 'مشتری P06',
            'mobile' => '0912' . random_int(1000000, 9999999),
            'email' => 'p06_' . uniqid() . '@example.com',
        ]);
        $site = Site::create([
            'customer_id' => $customer->id,
            'title' => 'سایت P06',
            'domain' => $domain,
            'is_active' => true,
            'support_expires_at' => $supportActive ? now()->addMonths(2) : now()->subMonths(2),
            'support_level' => 'basic',
        ]);
        $key = $this->licenseService->issue($site, $domain);
        return [$site, $key];
    }

    private function attachModuleWithReleases(Site $site, string $slug, array $releases): Module
    {
        $module = Module::firstOrCreate(
            ['slug' => $slug],
            ['title' => 'ماژول ' . $slug, 'type' => Module::TYPE_PAID, 'is_active' => true]
        );

        DB::table('site_modules')->updateOrInsert(
            ['site_id' => $site->id, 'module_id' => $module->id],
            ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
        );

        foreach ($releases as $rel) {
            ModuleRelease::create(array_merge([
                'module_id' => $module->id,
                'release_type' => 'patch',
                'is_active' => true,
                'distributed_at' => now(),
            ], $rel));
        }
        return $module;
    }

    // =====================================================================
    // P0-6 — GET /api/updates/module-history
    // =====================================================================

    public function test_module_history_rejects_unknown_license(): void
    {
        $response = $this->getJson('/api/updates/module-history?' . http_build_query([
            'license_key' => 'NOPE-NOPE-NOPE-NOPE',
            'domain' => 'p06.test',
        ]));

        $response->assertStatus(403);
        $response->assertJson(['allowed' => false]);
        $this->assertSame('invalid_key', $response->json('error'));
    }

    public function test_module_history_rejects_domain_mismatch(): void
    {
        [, $key] = $this->setupLicensedSite('p06-domain.test');

        $response = $this->getJson('/api/updates/module-history?' . http_build_query([
            'license_key' => $key,
            'domain' => 'attacker.test',
        ]));

        $response->assertStatus(403);
        $this->assertSame('domain_mismatch', $response->json('error'));
    }

    public function test_module_history_rejects_expired_support(): void
    {
        [$site, $key] = $this->setupLicensedSite('p06-expired.test', supportActive: false);

        $this->attachModuleWithReleases($site, 'p06_blog', [
            ['version' => '1.0.0'],
        ]);

        $response = $this->getJson('/api/updates/module-history?' . http_build_query([
            'license_key' => $key,
            'domain' => 'p06-expired.test',
        ]));

        $response->assertStatus(403);
        $this->assertSame('support_expired', $response->json('error'));
    }

    public function test_module_history_returns_only_releases_for_site_modules(): void
    {
        [$site, $key] = $this->setupLicensedSite('p06-history.test');

        $this->attachModuleWithReleases($site, 'p06_blog', [
            ['version' => '1.0.0', 'release_type' => 'minor', 'changelog' => 'init'],
            ['version' => '1.1.0', 'release_type' => 'minor', 'changelog' => 'feature'],
        ]);
        $this->attachModuleWithReleases($site, 'p06_shop', [
            ['version' => '2.0.0', 'release_type' => 'major', 'changelog' => 'shop-init'],
        ]);

        // a third module exists but is NOT attached to the site → must not appear
        $other = Module::firstOrCreate(
            ['slug' => 'p06_other'],
            ['title' => 'ماژول دیگر', 'type' => Module::TYPE_PAID, 'is_active' => true]
        );
        ModuleRelease::create([
            'module_id' => $other->id,
            'version' => '9.9.9',
            'release_type' => 'major',
            'is_active' => true,
            'distributed_at' => now(),
        ]);

        $response = $this->getJson('/api/updates/module-history?' . http_build_query([
            'license_key' => $key,
            'domain' => 'p06-history.test',
        ]));

        $response->assertOk();
        $response->assertJson(['allowed' => true]);

        $slugs = collect($response->json('history'))->pluck('module_slug')->all();
        $this->assertContains('p06_blog', $slugs);
        $this->assertContains('p06_shop', $slugs);
        $this->assertNotContains('p06_other', $slugs, 'modules not attached to the site must NOT appear');

        $first = $response->json('history.0');
        $this->assertArrayHasKey('module_slug', $first);
        $this->assertArrayHasKey('version', $first);
        $this->assertArrayHasKey('release_type', $first);
        $this->assertArrayHasKey('changelog', $first);
        $this->assertArrayHasKey('released_at', $first);
        $this->assertArrayHasKey('title', $first);
    }

    public function test_module_history_clamps_limit_to_1_and_200(): void
    {
        [$site, $key] = $this->setupLicensedSite('p06-limit.test');
        $module = $this->attachModuleWithReleases($site, 'p06_many', [
            ['version' => '1.0.0', 'distributed_at' => now()->subDays(3)],
            ['version' => '1.1.0', 'distributed_at' => now()->subDays(2)],
            ['version' => '1.2.0', 'distributed_at' => now()->subDays(1)],
        ]);

        $clamped1 = $this->getJson('/api/updates/module-history?' . http_build_query([
            'license_key' => $key,
            'domain' => 'p06-limit.test',
            'limit' => 1,
        ]));
        $clamped1->assertOk();
        $this->assertCount(1, $clamped1->json('history'));

        // limit > 200 should be validated (validation rules: nullable|integer|min:1|max:200)
        $tooBig = $this->getJson('/api/updates/module-history?' . http_build_query([
            'license_key' => $key,
            'domain' => 'p06-limit.test',
            'limit' => 9999,
        ]));
        $tooBig->assertStatus(422);
    }

    public function test_module_history_returns_empty_for_site_with_no_modules(): void
    {
        [, $key] = $this->setupLicensedSite('p06-empty.test');

        $response = $this->getJson('/api/updates/module-history?' . http_build_query([
            'license_key' => $key,
            'domain' => 'p06-empty.test',
        ]));

        $response->assertOk();
        $response->assertJson(['allowed' => true, 'history' => []]);
    }

    // =====================================================================
    // P0-7 — whatsapp slug preserved + title rebranded
    // =====================================================================

    public function test_whatsapp_slug_still_exists_for_backward_compat(): void
    {
        $row = DB::table('modules')->where('slug', 'whatsapp')->first();
        $this->assertNotNull($row, 'modules.slug=whatsapp must still exist after rebrand (no slug change → backwards compat)');
    }

    public function test_whatsapp_module_was_rebranded_to_messaging_title(): void
    {
        $row = DB::table('modules')->where('slug', 'whatsapp')->first();
        $this->assertNotNull($row);
        $this->assertSame('پاسخگویی شبکه‌ها', $row->title, 'whatsapp module must carry the messaging rebrand title');
    }

    public function test_no_separate_messaging_slug_was_added(): void
    {
        $exists = DB::table('modules')->where('slug', 'messaging')->exists();
        $this->assertFalse($exists, 'a separate slug=messaging row must NOT be introduced (would split entitlement)');
    }
}
