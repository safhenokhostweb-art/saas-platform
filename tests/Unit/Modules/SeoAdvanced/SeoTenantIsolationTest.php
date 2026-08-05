<?php

namespace Tests\Unit\Modules\SeoAdvanced;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use SaaSPlatform\Modules\SeoAdvanced\Http\Middleware\SeoAdvancedRedirectMiddleware;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedAudit;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedKeyword;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedRedirect;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedSetting;
use SaaSPlatform\Modules\SeoAdvanced\Services\MetaBuilder;
use SaaSPlatform\Modules\SeoAdvanced\Services\SeoReportService;
use Tests\TestCase;

/**
 * Phase 18-C — Part 7: SEO Tenant Isolation scenarios — no site's SEO
 * configuration/history ever leaks into another's, across every table
 * this module owns.
 */
class SeoTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_are_isolated_per_site(): void
    {
        SeoAdvancedSetting::create(['site_id' => 10, 'meta_title' => 'سایت ده']);
        SeoAdvancedSetting::create(['site_id' => 20, 'meta_title' => 'سایت بیست']);

        $metaFor10 = (new MetaBuilder())->build(10);
        $metaFor20 = (new MetaBuilder())->build(20);

        $this->assertSame('سایت ده', $metaFor10['title']);
        $this->assertSame('سایت بیست', $metaFor20['title']);
    }

    public function test_redirects_are_isolated_per_site(): void
    {
        SeoAdvancedRedirect::create(['site_id' => 1, 'from_path' => '/shared-path', 'to_path' => '/site-1-target', 'status_code' => 301, 'is_active' => true]);
        SeoAdvancedRedirect::create(['site_id' => 2, 'from_path' => '/shared-path', 'to_path' => '/site-2-target', 'status_code' => 301, 'is_active' => true]);

        // The runtime middleware only ever resolves the single-tenant
        // site_id=1 convention (Phase 18-B) — site 2's identically-pathed
        // redirect must never leak into what site 1's middleware serves.
        $response = (new SeoAdvancedRedirectMiddleware())->handle(
            Request::create('/shared-path', 'GET'),
            fn (Request $r) => 'next-called'
        );

        $this->assertStringEndsWith('/site-1-target', $response->headers->get('Location'));
    }

    public function test_audits_are_isolated_per_site(): void
    {
        SeoAdvancedAudit::create(['site_id' => 30, 'page_url' => '/x', 'score' => 90]);
        SeoAdvancedAudit::create(['site_id' => 31, 'page_url' => '/x', 'score' => 10]);

        $this->assertSame(90, SeoAdvancedAudit::where('site_id', 30)->first()->score);
        $this->assertSame(10, SeoAdvancedAudit::where('site_id', 31)->first()->score);
        $this->assertSame(1, SeoAdvancedAudit::where('site_id', 30)->count());
    }

    public function test_deleting_one_sites_redirects_never_touches_another_sites(): void
    {
        SeoAdvancedRedirect::create(['site_id' => 40, 'from_path' => '/a', 'to_path' => '/b', 'status_code' => 301]);
        $other = SeoAdvancedRedirect::create(['site_id' => 41, 'from_path' => '/a', 'to_path' => '/c', 'status_code' => 301]);

        SeoAdvancedRedirect::where('site_id', 40)->delete();

        $this->assertDatabaseHas('seo_advanced_redirects', ['id' => $other->id]);
        $this->assertSame(0, SeoAdvancedRedirect::where('site_id', 40)->count());
    }

    public function test_keywords_are_isolated_per_site(): void
    {
        SeoAdvancedKeyword::create(['site_id' => 50, 'keyword' => 'کفش مردانه']);
        SeoAdvancedKeyword::create(['site_id' => 51, 'keyword' => 'کفش زنانه']);

        $this->assertSame(1, SeoAdvancedKeyword::where('site_id', 50)->count());
        $this->assertSame('کفش مردانه', SeoAdvancedKeyword::where('site_id', 50)->first()->keyword);
        $this->assertSame('کفش زنانه', SeoAdvancedKeyword::where('site_id', 51)->first()->keyword);
    }

    public function test_reports_never_mix_data_across_sites(): void
    {
        SeoAdvancedAudit::create(['site_id' => 60, 'page_url' => '/x', 'score' => 95]);
        SeoAdvancedAudit::create(['site_id' => 61, 'page_url' => '/x', 'score' => 20]);
        SeoAdvancedKeyword::create(['site_id' => 60, 'keyword' => 'الف']);
        SeoAdvancedKeyword::create(['site_id' => 61, 'keyword' => 'ب']);
        SeoAdvancedKeyword::create(['site_id' => 61, 'keyword' => 'ج']);

        $reportFor60 = app(SeoReportService::class)->build(60);
        $reportFor61 = app(SeoReportService::class)->build(61);

        $this->assertSame(95, $reportFor60['latest_audit']->score);
        $this->assertSame(1, $reportFor60['keyword_count']);
        $this->assertSame(20, $reportFor61['latest_audit']->score);
        $this->assertSame(2, $reportFor61['keyword_count']);
    }
}
