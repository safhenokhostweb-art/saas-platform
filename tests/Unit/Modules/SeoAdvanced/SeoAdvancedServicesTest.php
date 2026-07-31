<?php

namespace Tests\Unit\Modules\SeoAdvanced;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use SaaSPlatform\CoreUtils\Support\ModuleManifestSchema;
use SaaSPlatform\Modules\SeoAdvanced\Contracts\SeoHeadRendererInterface;
use SaaSPlatform\Modules\SeoAdvanced\Events\SitemapUrlsRequested;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedAudit;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedRedirect;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedSetting;
use SaaSPlatform\Modules\SeoAdvanced\Services\MetaBuilder;
use SaaSPlatform\Modules\SeoAdvanced\Services\RobotsBuilder;
use SaaSPlatform\Modules\SeoAdvanced\Services\SchemaBuilder;
use SaaSPlatform\Modules\SeoAdvanced\Services\SeoAuditor;
use SaaSPlatform\Modules\SeoAdvanced\Services\SitemapBuilder;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Phase 18 — Part 10: SEO Advanced module — pure service-logic scenarios.
 * Exercises the real module classes (autoloaded via the composer path
 * repository registered in Phase 18), never App\Models — this module has
 * no dependency on the platform's Eloquent models at all.
 */
class SeoAdvancedServicesTest extends TestCase
{
    use RefreshDatabase;

    // ─── Scenario: manifest validation ─────────────────────────────────────

    public function test_real_module_manifest_passes_official_schema(): void
    {
        $manifest = json_decode(
            file_get_contents(base_path('packages/saas-platform/modules/seo-advanced/module.json')),
            true
        );

        $result = (new ModuleManifestSchema())->validate($manifest);

        $this->assertTrue($result['valid'], implode('; ', $result['errors']));
        $this->assertEmpty($result['warnings'], implode('; ', $result['warnings']));
    }

    // ─── Scenario: migration ────────────────────────────────────────────────

    public function test_module_migrations_create_expected_tables_and_columns(): void
    {
        $this->assertTrue(Schema::hasTable('seo_advanced_settings'));
        $this->assertTrue(Schema::hasColumns('seo_advanced_settings', [
            'site_id', 'meta_title', 'meta_description', 'robots_directive', 'canonical_url', 'schema_type',
        ]));

        $this->assertTrue(Schema::hasTable('seo_advanced_redirects'));
        $this->assertTrue(Schema::hasColumns('seo_advanced_redirects', ['site_id', 'from_path', 'to_path', 'status_code', 'is_active']));

        $this->assertTrue(Schema::hasTable('seo_advanced_audits'));
        $this->assertTrue(Schema::hasColumns('seo_advanced_audits', ['site_id', 'page_url', 'score', 'missing_meta']));
    }

    public function test_module_tables_are_independent_of_core_seo_tables(): void
    {
        // Core's own seo_redirects/seo_metas/seo_page_scores (Phase 1 audit
        // finding) are a completely different table from this module's —
        // both must be able to coexist without collision.
        $this->assertTrue(Schema::hasTable('seo_redirects'));
        $this->assertTrue(Schema::hasTable('seo_advanced_redirects'));
        $this->assertNotSame('seo_redirects', (new SeoAdvancedRedirect())->getTable());
    }

    // ─── Scenario: tenant isolation ─────────────────────────────────────────

    public function test_settings_redirects_and_audits_are_scoped_per_site(): void
    {
        SeoAdvancedSetting::create(['site_id' => 1, 'meta_title' => 'سایت یک']);
        SeoAdvancedSetting::create(['site_id' => 2, 'meta_title' => 'سایت دو']);
        SeoAdvancedRedirect::create(['site_id' => 1, 'from_path' => '/a', 'to_path' => '/b', 'status_code' => 301]);
        SeoAdvancedRedirect::create(['site_id' => 2, 'from_path' => '/a', 'to_path' => '/c', 'status_code' => 301]);
        SeoAdvancedAudit::create(['site_id' => 1, 'page_url' => '/x', 'score' => 90]);
        SeoAdvancedAudit::create(['site_id' => 2, 'page_url' => '/x', 'score' => 40]);

        $this->assertSame('سایت یک', SeoAdvancedSetting::where('site_id', 1)->first()->meta_title);
        $this->assertSame('سایت دو', SeoAdvancedSetting::where('site_id', 2)->first()->meta_title);
        $this->assertSame('/b', SeoAdvancedRedirect::where('site_id', 1)->first()->to_path);
        $this->assertSame('/c', SeoAdvancedRedirect::where('site_id', 2)->first()->to_path);
        $this->assertSame(90, SeoAdvancedAudit::where('site_id', 1)->first()->score);
        $this->assertSame(40, SeoAdvancedAudit::where('site_id', 2)->first()->score);
    }

    // ─── Scenario: meta rendering ───────────────────────────────────────────

    public function test_meta_builder_renders_title_description_canonical_and_og(): void
    {
        SeoAdvancedSetting::create([
            'site_id' => 10, 'meta_title' => 'فروشگاه تست', 'meta_description' => 'توضیح تست',
            'canonical_url' => 'https://example.com/', 'canonical_strategy' => SeoAdvancedSetting::CANONICAL_CUSTOM,
            'og_image_url' => 'https://example.com/og.png',
            'robots_directive' => 'index,follow',
        ]);

        $meta = (new MetaBuilder())->build(10);
        $html = (new MetaBuilder())->renderHtml($meta);

        $this->assertStringContainsString('<title>فروشگاه تست</title>', $html);
        $this->assertStringContainsString('name="description" content="توضیح تست"', $html);
        $this->assertStringContainsString('rel="canonical" href="https://example.com/"', $html);
        $this->assertStringContainsString('property="og:image" content="https://example.com/og.png"', $html);
        $this->assertStringContainsString('name="robots" content="index,follow"', $html);
    }

    public function test_meta_builder_page_overrides_win_over_site_defaults(): void
    {
        SeoAdvancedSetting::create(['site_id' => 11, 'meta_title' => 'پیش‌فرض سایت']);

        $meta = (new MetaBuilder())->build(11, ['title' => 'عنوان اختصاصی صفحه']);

        $this->assertSame('عنوان اختصاصی صفحه', $meta['title']);
    }

    public function test_seo_head_renderer_combines_meta_and_schema(): void
    {
        SeoAdvancedSetting::create([
            'site_id' => 12, 'meta_title' => 'سایت تست', 'schema_type' => SeoAdvancedSetting::SCHEMA_WEBSITE,
        ]);

        $html = app(SeoHeadRendererInterface::class)->render(12);

        $this->assertStringContainsString('<title>سایت تست</title>', $html);
        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('WebSite', $html);
    }

    // ─── Robots.txt ──────────────────────────────────────────────────────────

    public function test_robots_builder_allows_by_default(): void
    {
        $content = (new RobotsBuilder())->generate(20, 'https://example.com/sitemap.xml');

        $this->assertStringContainsString('User-agent: *', $content);
        $this->assertStringContainsString('Allow: /', $content);
        $this->assertStringContainsString('Sitemap: https://example.com/sitemap.xml', $content);
    }

    public function test_robots_builder_disallows_when_noindex_configured(): void
    {
        SeoAdvancedSetting::create(['site_id' => 21, 'robots_directive' => 'noindex,nofollow']);

        $content = (new RobotsBuilder())->generate(21);

        $this->assertStringContainsString('Disallow: /', $content);
    }

    // ─── Schema.org ──────────────────────────────────────────────────────────

    public function test_schema_builder_produces_organization_json_ld(): void
    {
        SeoAdvancedSetting::create([
            'site_id' => 30, 'schema_type' => SeoAdvancedSetting::SCHEMA_ORGANIZATION,
            'organization_name' => 'شرکت تست', 'organization_logo_url' => 'https://example.com/logo.png',
        ]);

        $schema = (new SchemaBuilder())->build(30);

        $this->assertSame('Organization', $schema['@type']);
        $this->assertSame('شرکت تست', $schema['name']);
    }

    public function test_schema_builder_produces_local_business_json_ld(): void
    {
        SeoAdvancedSetting::create([
            'site_id' => 31, 'schema_type' => SeoAdvancedSetting::SCHEMA_LOCAL_BUSINESS,
            'organization_name' => 'فروشگاه تست', 'business_address' => 'تهران',
        ]);

        $schema = (new SchemaBuilder())->build(31);

        $this->assertSame('LocalBusiness', $schema['@type']);
        $this->assertSame('تهران', $schema['address']);
    }

    public function test_schema_builder_falls_back_to_website_when_no_settings_exist(): void
    {
        $schema = (new SchemaBuilder())->build(999999);

        $this->assertSame('WebSite', $schema['@type']);
    }

    // ─── Scenario: sitemap generation ───────────────────────────────────────

    public function test_sitemap_builder_generates_valid_xml_with_urls(): void
    {
        $xml = (new SitemapBuilder())->generate([
            ['loc' => 'https://example.com/', 'changefreq' => 'daily', 'priority' => 1.0],
            ['loc' => 'https://example.com/about', 'lastmod' => '2026-01-01'],
        ]);

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/</loc>', $xml);
        $this->assertStringContainsString('<loc>https://example.com/about</loc>', $xml);
        $this->assertStringContainsString('<changefreq>daily</changefreq>', $xml);
    }

    public function test_sitemap_urls_requested_event_is_the_only_source_of_urls(): void
    {
        Event::fake([SitemapUrlsRequested::class]);

        event(new SitemapUrlsRequested(siteId: 40));

        Event::assertDispatched(SitemapUrlsRequested::class, fn ($e) => $e->siteId === 40);
    }

    // ─── Scenario: SEO audit engine ─────────────────────────────────────────

    public function test_auditor_flags_missing_title_and_description(): void
    {
        $result = (new SeoAuditor())->audit(null, null, []);

        $this->assertContains('title', $result['missing_meta']);
        $this->assertContains('description', $result['missing_meta']);
        $this->assertLessThan(100, $result['score']);
    }

    public function test_auditor_flags_missing_image_alt_text(): void
    {
        $title = str_repeat('a', 45);
        $description = str_repeat('b', 100);

        $result = (new SeoAuditor())->audit($title, $description, ['متن جایگزین', '', null]);

        $this->assertCount(2, $result['missing_alt_images']);
    }

    public function test_auditor_gives_full_score_for_a_well_optimized_page(): void
    {
        $title = str_repeat('a', 45);
        $description = str_repeat('b', 100);

        $result = (new SeoAuditor())->audit($title, $description, ['alt text']);

        $this->assertSame(100, $result['score']);
        $this->assertEmpty($result['missing_meta']);
        $this->assertEmpty($result['missing_alt_images']);
    }

    public function test_audit_and_store_persists_a_seo_advanced_audit_row(): void
    {
        $audit = (new SeoAuditor())->auditAndStore(50, '/test-page', 'عنوان', 'توضیح', []);

        $this->assertDatabaseHas('seo_advanced_audits', ['id' => $audit->id, 'site_id' => 50, 'page_url' => '/test-page']);
    }
}
