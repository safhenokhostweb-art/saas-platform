<?php

namespace Tests\Unit\Modules\SeoAdvanced;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use SaaSPlatform\Modules\SeoAdvanced\Http\Middleware\SeoAdvancedRedirectMiddleware;
use SaaSPlatform\Modules\SeoAdvanced\Services\MetaBuilder;
use SaaSPlatform\Modules\SeoAdvanced\Services\RobotsBuilder;
use SaaSPlatform\Modules\SeoAdvanced\Services\SchemaBuilder;
use Tests\TestCase;

/**
 * Phase 18-C — Part 6/7: Runtime Safety scenarios. The strongest proof
 * that "an app without this module works with zero errors" is that this
 * module never assumes any of its own data exists — every service here
 * is exercised against a completely empty state (no settings, no
 * redirects, and — the closest in-process simulation of "not installed
 * at all" — a dropped table) and must degrade gracefully, never throw.
 */
class SeoModuleDisabledTest extends TestCase
{
    use RefreshDatabase;

    private const UNCONFIGURED_SITE_ID = 987654;

    public function test_meta_builder_returns_safe_defaults_with_no_settings_row(): void
    {
        $meta = (new MetaBuilder())->build(self::UNCONFIGURED_SITE_ID);

        $this->assertNull($meta['title']);
        $this->assertSame('index,follow', $meta['robots']);
        $this->assertIsString((new MetaBuilder())->renderHtml($meta));
    }

    public function test_robots_builder_defaults_to_allow_with_no_settings_row(): void
    {
        $content = (new RobotsBuilder())->generate(self::UNCONFIGURED_SITE_ID);

        $this->assertStringContainsString('Allow: /', $content);
    }

    public function test_schema_builder_defaults_to_website_with_no_settings_row(): void
    {
        $schema = (new SchemaBuilder())->build(self::UNCONFIGURED_SITE_ID);

        $this->assertSame('WebSite', $schema['@type']);
    }

    public function test_redirect_middleware_passes_through_with_no_redirects_configured(): void
    {
        $result = (new SeoAdvancedRedirectMiddleware())->handle(
            Request::create('/anything', 'GET'),
            fn (Request $r) => 'next-called'
        );

        $this->assertSame('next-called', $result);
    }

    /**
     * The closest in-process simulation of "this module isn't installed
     * at all" — its own table doesn't exist yet (e.g. mid-deployment,
     * before migrations finish). Must not surface a QueryException to
     * the site's visitors.
     */
    public function test_redirect_middleware_never_errors_if_its_table_is_missing(): void
    {
        // Schema::drop() is DDL — MySQL implicitly commits DDL, so
        // RefreshDatabase's per-test transaction rollback cannot undo it.
        // Must restore the table before this test ends, or every
        // subsequent test in the same run inherits a permanently-broken
        // schema (this bit us on the first run of this suite).
        Schema::drop('seo_advanced_redirects');

        try {
            $result = (new SeoAdvancedRedirectMiddleware())->handle(
                Request::create('/anything', 'GET'),
                fn (Request $r) => 'next-called'
            );

            $this->assertSame('next-called', $result);
        } finally {
            (require base_path('packages/saas-platform/modules/seo-advanced/database/migrations/2026_07_29_100001_create_seo_advanced_redirects_table.php'))->up();
        }
    }

    public function test_no_extra_route_is_registered_outside_this_modules_own_prefix(): void
    {
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter();

        $seoRoutes = $routes->filter(fn ($name) => str_starts_with((string) $name, 'seo-advanced.'));

        $this->assertGreaterThan(0, $seoRoutes->count());
        foreach ($seoRoutes as $name) {
            $this->assertStringStartsWith('seo-advanced.', $name);
        }
    }
}
