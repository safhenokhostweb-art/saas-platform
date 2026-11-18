<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeMarketplace;
use App\Services\ThemeBuilder\BlockRenderer;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Database\Seeders\SolarIndustryThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SaaSPlatform\ThemeRuntime\Access\AssetAccessCatalog;
use SaaSPlatform\ThemeRuntime\Access\AssetRef;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\TemplateLibrary;
use Tests\TestCase;

/**
 * ADR-084: «قالب صنعت برق» is a standard platform Theme assembled from the
 * template library (PLATFORM-THEME-SPEC-v1 blueprints) and the solar-dark
 * token preset — canonical widgets configured by props, nothing theme-owned.
 */
class SolarIndustryThemeSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedTheme(): Theme
    {
        $this->seed(SolarIndustryThemeSeeder::class);

        return Theme::where('slug', SolarIndustryThemeSeeder::SLUG)->whereNull('site_id')->firstOrFail();
    }

    /** @return list<array> */
    private function flatten(array $blocks): array
    {
        $all = [];
        foreach ($blocks as $block) {
            $all[] = $block;
            $all = array_merge($all, $this->flatten($block['children'] ?? []));
        }
        return $all;
    }

    public function test_it_seeds_one_labelled_platform_theme_idempotently(): void
    {
        $theme = $this->seedTheme();
        $this->assertSame('قالب صنعت برق', $theme->name);
        $this->assertSame([Theme::TYPE_FULL, Theme::CATEGORY_INDUSTRIAL, Theme::SOURCE_SEEDER, Theme::STATUS_PUBLISHED], [$theme->type, $theme->category, $theme->source, $theme->status]);
        $this->assertNull($theme->site_id, 'a shared platform theme, never a site clone');
        // Owner rule 2026-11-17: never listed in the tenant marketplace by default.
        $this->assertFalse((bool) $theme->is_marketplace);
        $this->assertDatabaseHas('theme_marketplace', ['theme_id' => $theme->id, 'is_featured' => false, 'is_bestseller' => false]);

        $this->seed(SolarIndustryThemeSeeder::class);
        $this->assertSame(1, Theme::where('slug', SolarIndustryThemeSeeder::SLUG)->count());
        $this->assertSame(1, ThemeMarketplace::where('theme_id', $theme->id)->count());
        $this->assertSame($theme->pages, $theme->fresh()->pages, 'a reseed writes the same content');
    }

    public function test_a_reseed_keeps_the_admins_listing_and_price(): void
    {
        $theme = $this->seedTheme();
        // The admin lists it as a paid product («فروشگاه قالب ← Listing»).
        $theme->update(['is_marketplace' => true, 'marketplace_price' => 4_900_000]);
        ThemeMarketplace::where('theme_id', $theme->id)->update(['annual_price' => 1_200_000, 'is_featured' => true]);

        $this->seed(SolarIndustryThemeSeeder::class);

        $theme->refresh();
        $this->assertTrue((bool) $theme->is_marketplace);
        $this->assertSame(4_900_000, $theme->marketplace_price);
        $meta = ThemeMarketplace::where('theme_id', $theme->id)->sole();
        $this->assertSame(1_200_000, (int) $meta->annual_price);
        $this->assertTrue((bool) $meta->is_featured);
    }

    public function test_a_reseed_never_relists_a_theme_the_admin_unlisted(): void
    {
        $theme = $this->seedTheme();
        $theme->update(['is_marketplace' => false, 'marketplace_price' => 0]);

        $this->seed(SolarIndustryThemeSeeder::class);

        $this->assertFalse((bool) $theme->fresh()->is_marketplace);
    }

    public function test_the_content_is_the_library_blueprints_with_unique_ids(): void
    {
        $theme = $this->seedTheme();
        // MySQL's JSON column orders object keys itself, so compare page => slug, not order.
        $slugs = array_map(fn ($page) => $page['slug'], $theme->pages);
        $expected = array_map(fn (array $entry) => $entry[2], SolarIndustryThemeSeeder::PAGES);
        ksort($slugs);
        ksort($expected);
        $this->assertCount(24, $slugs);
        $this->assertSame($expected, $slugs);
        // The five 1.0.0 pages keep their keys and slugs, so installed sites upgrade cleanly.
        foreach (['home' => '', 'about' => 'about', 'products' => 'products', 'courses' => 'courses', 'contact' => 'contact'] as $key => $slug) {
            $this->assertSame($slug, $slugs[$key] ?? null, "1.0.0 page {$key} keeps its key and slug");
        }
        $this->assertTrue($theme->pages['home']['is_home']);
        $this->assertSame(1, count(array_filter($theme->pages, fn ($p) => $p['is_home'])));

        $this->assertSame('nav_header', $theme->header_blocks[0]['type']);
        $this->assertSame('nav_footer', $theme->footer_blocks[0]['type']);
        // assertEquals: the JSON column reorders object keys; values and list order must match.
        $this->assertEquals(TemplateLibrary::find('lib:solar-header')['blocks'][0]['props'], $theme->header_blocks[0]['props'], 'the header is the library blueprint, unchanged');

        $all = $this->flatten(array_merge($theme->header_blocks, $theme->footer_blocks, ...array_column($theme->pages, 'blocks')));
        $ids = array_column($all, 'id');
        $this->assertSame(count($ids), count(array_unique($ids)), 'block ids are unique inside the theme');
        $registry = BlockRegistry::all();
        foreach ($all as $block) {
            $this->assertArrayHasKey($block['type'], $registry, "{$block['type']} is a canonical registered widget");
        }
        $nonModuleTypes = array_filter(
            array_unique(array_column($all, 'type')),
            fn ($t) => !str_starts_with($t, 'module:')
        );
        $this->assertDoesNotMatchRegularExpression('/solar_|_solar|sanat|barq/i', implode(' ', $nonModuleTypes), 'no theme-specific widget types');
    }

    /**
     * 2026-11-17: 1.0.0 shipped 5 pages while the library held 24 solar page
     * templates (23 content pages + the auth canvas; 19 never reached it).
     * Guard: every page template of the solar-industry family is a page here.
     */
    public function test_every_solar_industry_page_template_in_the_library_is_a_page_of_the_theme(): void
    {
        $family = AssetAccessCatalog::section(AssetRef::KIND_TEMPLATE);
        $libraryPages = [];
        foreach (TemplateLibrary::documents() as $key => $doc) {
            if ($doc['type'] === 'page' && ($family[$key] ?? null) === 'solar-industry') {
                $libraryPages[] = $key;
            }
        }
        $themePages = array_column(SolarIndustryThemeSeeder::PAGES, 0);
        sort($libraryPages);
        sort($themePages);

        $this->assertCount(24, $libraryPages, "23 content pages + the auth canvas");
        $this->assertSame($libraryPages, $themePages);

        // The added content pages use their template's own route as slug; the
        // auth canvas (no route) is the `login` page the customer site renders at /login.
        foreach (SolarIndustryThemeSeeder::PAGES as $key => [$template, , $slug]) {
            if (in_array($key, ['home', 'about', 'products', 'courses', 'contact'], true)) {
                continue;
            }
            $route = TemplateLibrary::documents()[$template]['route'] ?? null;
            $this->assertSame($route ?? 'login', $slug, "{$template} slug");
        }
        $this->assertSame(['auth-solar-industrial', 'ورود و عضویت', 'login'], SolarIndustryThemeSeeder::PAGES['login']);
    }

    public function test_every_internal_page_link_resolves_to_a_page_of_the_theme(): void
    {
        $theme = $this->seedTheme();
        $json = json_encode([$theme->header_blocks, $theme->footer_blocks, $theme->pages], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        preg_match_all('#"/page/([a-z0-9-]+)#', $json, $m);
        $linked = array_values(array_unique($m[1]));
        $missing = array_values(array_diff($linked, array_column($theme->pages, 'slug')));

        $this->assertGreaterThanOrEqual(13, count($linked), 'the header, footer and pages link to many theme pages');
        $this->assertSame([], $missing, 'links to pages the theme does not have');
    }

    public function test_colours_come_from_the_solar_dark_preset(): void
    {
        $styles = $this->seedTheme()->global_styles;
        $preset = TemplateLibrary::tokenPresets()['solar-dark']['global_styles'];
        foreach ($preset['colors'] as $key => $value) {
            $this->assertSame($value, $styles['colors'][$key], $key);
        }
        $this->assertSame($preset['typography']['font_body'], $styles['typography']['font_body']);
        $this->assertSame(['rtl', 'fa-IR', false], [$styles['direction'], $styles['locale'], $styles['dark_mode']['enabled']]);
        $this->assertArrayNotHasKey('primary_color', $styles, 'no flat colour key overrides the preset');
    }

    public function test_every_zone_renders_through_the_canonical_renderer(): void
    {
        $theme = $this->seedTheme();
        $renderer = new BlockRenderer();
        $header = $renderer->renderBlock($theme->header_blocks[0]);
        $this->assertStringContainsString('tb-nav-header--mega-card', $header);
        $this->assertStringContainsString('<span class="tb-nav-header-logo-title">برند <span class="tb-nav-header-logo-accent">شما</span></span>', $header);
        foreach ($theme->pages as $key => $page) {
            $html = implode('', array_map(fn ($b) => $renderer->renderBlock($b), $page['blocks']));
            $this->assertNotSame('', trim($html), $key);
            $this->assertStringNotContainsString('tb-block-fallback', $html, $key);
        }
    }

    public function test_it_can_be_released_through_the_canonical_release_writer(): void
    {
        $theme = $this->seedTheme();
        $release = app(ThemeReleaseService::class)->release($theme, '1.0.0', 'قالب صنعت برق 1.0.0');
        $this->assertSame('1.0.0', $release->version);
        $this->assertSame($release->id, $theme->fresh()->current_release_id);
    }

    public function test_homepage_has_slider_and_calculator_page_has_solar_calculator_module(): void
    {
        $theme = $this->seedTheme();
        $homeTypes = array_column($theme->pages['home']['blocks'], 'type');
        $this->assertContains('slider', $homeTypes, 'homepage contains the slider block');

        $calcTypes = array_column($theme->pages['solar_calculator']['blocks'], 'type');
        $this->assertContains('module:solar_calculator', $calcTypes, 'solar_calculator page contains module:solar_calculator block');
    }
}
