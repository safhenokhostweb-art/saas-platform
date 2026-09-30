<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Customer;
use App\Models\PlatformLicense;
use App\Models\Site;
use App\Models\Theme;
use App\Models\ThemeLicense;
use App\Services\LicenseServerService;
use App\Services\ThemeBuilder\QualityGate\ThemeCommercialQualityGate;
use App\Services\ThemeBuilder\ThemeBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Real end-to-end coverage for the customer-facing Theme Marketplace
 * catalog ("قالب‌های مشتری" on customer-site, GET /api/theme-marketplace
 * here) — the exact gap a real local reproduction found: zero
 * PlatformLicense rows existed for the local test Site, so every
 * tenant.api request 403'd and the catalog showed nothing at all,
 * regardless of any Theme's publish/eligibility state.
 *
 * Two DISTINCT authorities are covered on purpose, never conflated:
 *  - PlatformLicense: SITE-level trust (this deployment is allowed to talk
 *    to the platform API at all). Missing/invalid => the whole catalog
 *    request fails, for every theme.
 *  - ThemeLicense: PER-THEME grant for a site (has "acquired" this theme).
 *    Missing only affects that one theme's has_license flag / download
 *    eligibility — never removes it from the browseable catalog.
 */
class ThemeMarketplaceCatalogVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeSite(string $domain): Site
    {
        $customer = Customer::create([
            'name' => 'Test Customer ' . uniqid(),
            'mobile' => '09' . random_int(100000000, 999999999),
        ]);

        return Site::create([
            'customer_id' => $customer->id,
            'title' => 'Test Site ' . uniqid(),
            'domain' => 'http://' . $domain,
            'is_active' => true,
        ]);
    }

    private function licenseHeaders(Site $site, string $domain): array
    {
        $key = app(LicenseServerService::class)->issue($site, $domain, now()->addYear());
        return [
            'Authorization' => 'Bearer ' . $key,
            'X-Tenant-Domain' => $domain,
            'Accept' => 'application/json',
        ];
    }

    private function publishedTheme(string $slug, string $name): Theme
    {
        $theme = Theme::create([
            'name' => $name,
            'slug' => $slug,
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_MANUAL,
            'category' => Theme::CATEGORY_LANDING,
            'status' => Theme::STATUS_DRAFT,
            'is_marketplace' => true,
            'license_type' => Theme::LICENSE_FREE,
            'global_styles' => app(ThemeBuilderService::class)->defaultGlobalStyles(),
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages' => ['home' => ['title' => 'Home', 'slug' => '', 'is_home' => true, 'blocks' => [
                ['id' => 'b1', 'type' => 'heading', 'props' => ['text' => $name, 'level' => 1]],
            ]]],
        ]);

        app(ThemeBuilderService::class)->publish($theme, '1.0.0', 'test publish');

        return $theme->fresh();
    }

    // ── The real, reproduced bug: missing PlatformLicense blocks EVERYTHING ──

    public function test_catalog_request_without_a_platform_license_is_rejected_for_every_theme(): void
    {
        $this->publishedTheme('catalog-vis-a', 'Theme A');

        $response = $this->getJson('/api/theme-marketplace', [
            'Authorization' => 'Bearer totally-invalid-key',
            'X-Tenant-Domain' => 'unlicensed-site.test',
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(403)->assertJson(['success' => false, 'allowed' => false]);
    }

    // ── Core scenario from the report: two published themes, both visible ──

    public function test_a_licensed_site_sees_every_published_commercially_eligible_theme(): void
    {
        $site = $this->makeSite('site-a.test');
        $themeA = $this->publishedTheme('catalog-vis-a', 'Theme A');

        $onlyA = $this->getJson('/api/theme-marketplace', $this->licenseHeaders($site, 'site-a.test'));
        $onlyA->assertOk();
        $this->assertCount(1, $onlyA->json('themes'));
        $this->assertSame('catalog-vis-a', $onlyA->json('themes.0.slug'));

        $themeB = $this->publishedTheme('catalog-vis-b', 'Theme B');

        $both = $this->getJson('/api/theme-marketplace', $this->licenseHeaders($site, 'site-a.test'));
        $both->assertOk();
        $slugs = array_column($both->json('themes'), 'slug');
        $this->assertContains('catalog-vis-a', $slugs);
        $this->assertContains('catalog-vis-b', $slugs);
    }

    public function test_a_draft_theme_never_appears_in_the_catalog(): void
    {
        $site = $this->makeSite('site-draft.test');
        $this->publishedTheme('catalog-vis-published', 'Published One');

        $draft = Theme::create([
            'name' => 'Still Draft', 'slug' => 'catalog-vis-draft', 'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_MANUAL, 'status' => Theme::STATUS_DRAFT, 'is_marketplace' => true,
            'global_styles' => [], 'header_blocks' => [], 'footer_blocks' => [], 'pages' => [],
        ]);

        $response = $this->getJson('/api/theme-marketplace', $this->licenseHeaders($site, 'site-draft.test'));
        $response->assertOk();
        $slugs = array_column($response->json('themes'), 'slug');
        $this->assertContains('catalog-vis-published', $slugs);
        $this->assertNotContains('catalog-vis-draft', $slugs);
    }

    // ── Per-theme license (has_license) never gates catalog VISIBILITY ──

    public function test_has_license_reflects_acquisition_without_hiding_unacquired_themes(): void
    {
        $site = $this->makeSite('site-license.test');
        $theme = $this->publishedTheme('catalog-vis-lic', 'Licensable Theme');

        $before = $this->getJson('/api/theme-marketplace', $this->licenseHeaders($site, 'site-license.test'));
        $before->assertOk();
        $this->assertFalse($before->json('themes.0.has_license'));

        ThemeLicense::grant($site->id, $theme->id);

        $after = $this->getJson('/api/theme-marketplace', $this->licenseHeaders($site, 'site-license.test'));
        $after->assertOk();
        $this->assertTrue($after->json('themes.0.has_license'));
    }

    // ── Two independent sites never see or share each other's ThemeLicense ──

    public function test_two_independent_sites_have_isolated_theme_licenses(): void
    {
        $siteA = $this->makeSite('isolation-a.test');
        $siteB = $this->makeSite('isolation-b.test');
        $theme = $this->publishedTheme('catalog-vis-iso', 'Isolation Theme');

        ThemeLicense::grant($siteA->id, $theme->id);

        $asA = $this->getJson('/api/theme-marketplace', $this->licenseHeaders($siteA, 'isolation-a.test'));
        $asB = $this->getJson('/api/theme-marketplace', $this->licenseHeaders($siteB, 'isolation-b.test'));

        $this->assertTrue($asA->json('themes.0.has_license'), 'Site A purchased it — must show licensed.');
        $this->assertFalse($asB->json('themes.0.has_license'), 'Site B never purchased it — must NOT inherit Site A\'s license.');
    }

    public function test_a_platform_license_scoped_to_one_domain_is_rejected_for_a_different_domain(): void
    {
        $site = $this->makeSite('bound-domain.test');
        $this->publishedTheme('catalog-vis-domain', 'Domain Bound Theme');
        $key = app(LicenseServerService::class)->issue($site, 'bound-domain.test', now()->addYear());

        $response = $this->getJson('/api/theme-marketplace', [
            'Authorization' => 'Bearer ' . $key,
            'X-Tenant-Domain' => 'a-different-domain.test',
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(403);
        $this->assertSame('domain_mismatch', $response->json('error'));
    }
}
