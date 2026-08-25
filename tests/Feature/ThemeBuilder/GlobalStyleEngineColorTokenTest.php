<?php

namespace Tests\Feature\ThemeBuilder;

use App\Services\ThemeBuilder\GlobalStyleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Theme Builder commercial polish audit: GlobalStyleEngine read colors from
 * global_styles['colors']['primary'], a shape no theme in production ever
 * writes — MarketplaceThemeSeedCommand::styles() and the AI generator both
 * store flat keys (primary_color, secondary_color, ...). Every theme's admin
 * preview / marketplace screenshot silently fell back to the platform
 * default indigo (#4f46e5) regardless of the theme's actual configured
 * colors. Real customer sites were unaffected (canvas-theme.blade.php
 * already read the flat keys first) — this was a preview/screenshot-only bug.
 */
class GlobalStyleEngineColorTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_flat_primary_color_is_applied_to_the_css_variable(): void
    {
        $engine = $this->app->make(GlobalStyleEngine::class);

        $vars = $engine->getCssVariables([
            'primary_color'   => '#92400e',
            'secondary_color' => '#451a03',
            'background_color'=> '#fffbeb',
            'text_color'      => '#451a03',
            'font_family'     => 'Vazirmatn',
        ]);

        $this->assertSame('#92400e', $vars['--tb-primary']);
        $this->assertSame('#451a03', $vars['--tb-secondary']);
        $this->assertSame('#fffbeb', $vars['--tb-background']);
        $this->assertSame('#451a03', $vars['--tb-text']);
        $this->assertSame('Vazirmatn', $vars['--tb-font-heading']);
    }

    public function test_distinct_themes_produce_distinct_primary_colors(): void
    {
        $engine = $this->app->make(GlobalStyleEngine::class);

        $restaurant = $engine->getCssVariables(['primary_color' => '#92400e']);
        $realEstate = $engine->getCssVariables(['primary_color' => '#0c4a6e']);

        $this->assertNotSame($restaurant['--tb-primary'], $realEstate['--tb-primary']);
        $this->assertSame('#92400e', $restaurant['--tb-primary']);
        $this->assertSame('#0c4a6e', $realEstate['--tb-primary']);
    }

    public function test_legacy_nested_colors_shape_still_works_as_a_fallback(): void
    {
        $engine = $this->app->make(GlobalStyleEngine::class);

        $vars = $engine->getCssVariables([
            'colors' => ['primary' => '#16a085', 'secondary' => '#0e6655'],
        ]);

        $this->assertSame('#16a085', $vars['--tb-primary']);
        $this->assertSame('#0e6655', $vars['--tb-secondary']);
    }

    public function test_theme_with_no_colors_at_all_falls_back_to_platform_default(): void
    {
        $engine = $this->app->make(GlobalStyleEngine::class);

        $vars = $engine->getCssVariables([]);

        $this->assertNotEmpty($vars['--tb-primary']);
    }

    public function test_render_css_block_embeds_the_theme_primary_color(): void
    {
        $engine = $this->app->make(GlobalStyleEngine::class);

        $css = $engine->renderCssBlock(['primary_color' => '#0e7490']);

        $this->assertStringContainsString('--tb-primary: #0e7490;', $css);
    }
}
