<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeMarketplace;
use App\Services\ThemeBuilder\BlockRenderer;
use App\Services\ThemeBuilder\ThemeReleaseService;
use Database\Seeders\SolarIndustryThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertTrue((bool) $theme->is_marketplace);
        $this->assertDatabaseHas('theme_marketplace', ['theme_id' => $theme->id, 'is_featured' => false, 'is_bestseller' => false]);

        $this->seed(SolarIndustryThemeSeeder::class);
        $this->assertSame(1, Theme::where('slug', SolarIndustryThemeSeeder::SLUG)->count());
        $this->assertSame(1, ThemeMarketplace::where('theme_id', $theme->id)->count());
        $this->assertSame($theme->pages, $theme->fresh()->pages, 'a reseed writes the same content');
    }

    public function test_the_content_is_the_library_blueprints_with_unique_ids(): void
    {
        $theme = $this->seedTheme();
        // MySQL's JSON column orders object keys itself, so compare page => slug, not order.
        $slugs = array_map(fn ($page) => $page['slug'], $theme->pages);
        ksort($slugs);
        $this->assertSame(['about' => 'about', 'contact' => 'contact', 'courses' => 'courses', 'home' => '', 'products' => 'products'], $slugs);
        $this->assertTrue($theme->pages['home']['is_home']);

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
        $this->assertDoesNotMatchRegularExpression('/solar_|_solar|sanat|barq/i', implode(' ', array_unique(array_column($all, 'type'))), 'no theme-specific widget types');
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
}
