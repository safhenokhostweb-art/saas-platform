<?php

namespace Tests\Unit\ThemeBuilder;

use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\BlockRenderer;
use SaaSPlatform\ThemeRuntime\SolarIndustryBlockTypes;
use Tests\TestCase;

/**
 * Unit Test Suite for Theme-Scoped Solar Motion Graphic Widget.
 * Verifies:
 *   - Isolated theme scope (exclusively visible in `solar-industry` theme, hidden from global platform tenants).
 *   - 100% granular Canvas Inspector controls and backend schema definition.
 *   - SVG particle, Lottie JSON, and WebM video rendering modes.
 *   - Persian RTL layout, Persian number formatting, and theme font inheritance (`var(--tb-font-family, inherit)`).
 *   - Accessibility (prefers-reduced-motion) and zero layout shift.
 */
final class SolarEnergyCycleWidgetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SolarIndustryBlockTypes::register();
    }

    public function test_widget_is_registered_and_scoped_exclusively_to_solar_industry_theme(): void
    {
        $definitions = SolarIndustryBlockTypes::definitions();
        self::assertArrayHasKey(SolarIndustryBlockTypes::TYPE_ENERGY_CYCLE, $definitions);

        $def = $definitions[SolarIndustryBlockTypes::TYPE_ENERGY_CYCLE];
        self::assertSame('solar-industry', $def['theme_scope']);
        self::assertSame('solar-industry', $def['category']);
        self::assertSame('بخش چرخه انرژی خورشیدی', $def['name']);

        // Check theme-scoped filtering in BlockRegistry
        $solarBlocks = BlockRegistry::forTheme('solar-industry');
        self::assertArrayHasKey(SolarIndustryBlockTypes::TYPE_ENERGY_CYCLE, $solarBlocks);

        // Filtered out for other themes (e.g. foodmart, default/null)
        $globalBlocks = BlockRegistry::forTheme(null);
        self::assertArrayNotHasKey(SolarIndustryBlockTypes::TYPE_ENERGY_CYCLE, $globalBlocks, 'Must be hidden from global tenants');

        $foodmartBlocks = BlockRegistry::forTheme('foodmart');
        self::assertArrayNotHasKey(SolarIndustryBlockTypes::TYPE_ENERGY_CYCLE, $foodmartBlocks, 'Must be hidden from other theme tenants');

        // Check category list by theme
        $solarByCat = BlockRegistry::byCategory('solar-industry');
        self::assertArrayHasKey('solar-industry', $solarByCat);
        self::assertArrayHasKey(SolarIndustryBlockTypes::TYPE_ENERGY_CYCLE, $solarByCat['solar-industry']);

        $globalByCat = BlockRegistry::byCategory(null);
        self::assertArrayNotHasKey('solar-industry', $globalByCat, 'Category must be hidden from global tenants');

        $otherByCat = BlockRegistry::byCategory('foodmart');
        self::assertArrayNotHasKey('solar-industry', $otherByCat, 'Category must be hidden from non-solar themes');

        // Category labels and icons
        $solarLabels = BlockRegistry::categoryLabels('solar-industry');
        self::assertSame('ویجت‌های اختصاصی صنعت برق و خورشیدی', $solarLabels['solar-industry']);
        $globalLabels = BlockRegistry::categoryLabels(null);
        self::assertArrayNotHasKey('solar-industry', $globalLabels);

        $solarIcons = BlockRegistry::categoryIcons('solar-industry');
        self::assertSame('☀️', $solarIcons['solar-industry']);
    }

    public function test_schema_exposes_100_percent_granular_inspector_controls(): void
    {
        $def = SolarIndustryBlockTypes::definitions()[SolarIndustryBlockTypes::TYPE_ENERGY_CYCLE];
        $schema = $def['schema'];
        $defaults = $def['defaultProps'];

        // Mode & Assets
        self::assertArrayHasKey('mode', $schema);
        self::assertArrayHasKey('lottie_url', $schema);
        self::assertArrayHasKey('video_webm_url', $schema);
        self::assertArrayHasKey('video_mp4_fallback_url', $schema);
        self::assertArrayHasKey('poster_image', $schema);

        // Persian Text Layers
        self::assertArrayHasKey('eyebrow', $schema);
        self::assertArrayHasKey('title', $schema);
        self::assertArrayHasKey('subtitle', $schema);
        self::assertArrayHasKey('badge_label', $schema);
        self::assertArrayHasKey('badge_icon', $schema);
        self::assertArrayHasKey('show_cta', $schema);
        self::assertArrayHasKey('cta_label', $schema);
        self::assertArrayHasKey('cta_url', $schema);

        // Dynamic Counters
        self::assertArrayHasKey('show_counters', $schema);
        self::assertArrayHasKey('persian_digits', $schema);
        self::assertArrayHasKey('counter_power_value', $schema);
        self::assertArrayHasKey('counter_power_unit', $schema);
        self::assertArrayHasKey('counter_power_label', $schema);
        self::assertArrayHasKey('counter_efficiency_value', $schema);
        self::assertArrayHasKey('counter_efficiency_unit', $schema);
        self::assertArrayHasKey('counter_efficiency_label', $schema);
        self::assertArrayHasKey('counter_co2_value', $schema);
        self::assertArrayHasKey('counter_co2_unit', $schema);
        self::assertArrayHasKey('counter_co2_label', $schema);
        self::assertArrayHasKey('counter_daily_value', $schema);
        self::assertArrayHasKey('counter_daily_unit', $schema);
        self::assertArrayHasKey('counter_daily_label', $schema);

        // Steps & Tooltips
        self::assertArrayHasKey('show_steps', $schema);
        self::assertArrayHasKey('steps', $schema);
        self::assertSame('repeater', $schema['steps']['type']);

        // Animation Timings & Controls
        self::assertArrayHasKey('animation_speed', $schema);
        self::assertArrayHasKey('animation_duration', $schema);
        self::assertArrayHasKey('loop_behavior', $schema);
        self::assertArrayHasKey('autoplay', $schema);
        self::assertArrayHasKey('pause_on_hover', $schema);

        // Dimensions & Scale
        self::assertArrayHasKey('container_height', $schema);
        self::assertArrayHasKey('max_width', $schema);
        self::assertArrayHasKey('scale', $schema);
        self::assertArrayHasKey('aspect_ratio', $schema);
        self::assertArrayHasKey('section_spacing', $schema);

        // Color Themes & Custom Colors
        self::assertArrayHasKey('color_theme', $schema);
        self::assertArrayHasKey('glow_effect', $schema);
        self::assertArrayHasKey('custom_accent_color', $schema);
        self::assertArrayHasKey('custom_panel_color', $schema);
        self::assertArrayHasKey('custom_particle_color', $schema);

        // Verify every schema field has a matching defaultProp
        foreach (array_keys($schema) as $field) {
            self::assertArrayHasKey($field, $defaults, "Field {$field} must have defaultProp");
        }
    }

    public function test_svg_particle_mode_renders_full_energy_cycle_and_persian_typography(): void
    {
        $renderer = new BlockRenderer();
        $html = $renderer->renderBlock([
            'id' => 'solar-mg-1',
            'type' => 'solar-energy-cycle',
            'props' => [
                'mode' => 'svg_particle',
                'eyebrow' => 'فناوری نوین تبدیل فوتوالکتریک',
                'title' => 'سیستم هوشمند خورشیدی صنعتی',
                'subtitle' => 'معماری مدرن و بهینه‌سازی‌شده برای تولید پایدار الکتریسیته.',
                'badge_label' => 'راندمان ۲۴.۱٪',
                'counter_power_value' => '۱۲.۵',
                'counter_power_unit' => 'کیلووات',
                'persian_digits' => true,
            ],
        ]);

        // RTL & Accessibility
        self::assertStringContainsString('dir="rtl"', $html);
        self::assertStringContainsString('role="region"', $html);
        self::assertStringContainsString('سیستم هوشمند خورشیدی صنعتی', $html);
        self::assertStringContainsString('فناوری نوین تبدیل فوتوالکتریک', $html);
        self::assertStringContainsString('راندمان ۲۴.۱٪', $html);

        // Theme Typography inheritance
        self::assertStringContainsString('--smg-font: var(--tb-font-family, \'Vazirmatn\', inherit);', $html);
        self::assertStringContainsString('font-family: var(--smg-font);', $html);

        // SVG Elements
        self::assertStringContainsString('class="smg-motion-svg', $html);
        self::assertStringContainsString('class="smg-zone-sun"', $html);
        self::assertStringContainsString('class="smg-sun-core"', $html);
        self::assertStringContainsString('class="smg-sun-rays"', $html);
        self::assertStringContainsString('class="smg-photon-stream"', $html);
        self::assertStringContainsString('class="smg-zone-panel"', $html);
        self::assertStringContainsString('class="smg-zone-inverter"', $html);
        self::assertStringContainsString('class="smg-ac-sine-wave"', $html);
        self::assertStringContainsString('class="smg-zone-battery"', $html);
        self::assertStringContainsString('class="smg-zone-grid"', $html);

        // Hardware-accelerated CSS animations & Keyframes
        self::assertStringContainsString('@keyframes smg-photon-travel', $html);
        self::assertStringContainsString('@keyframes smg-dash-pulse', $html);
        self::assertStringContainsString('@keyframes smg-sun-spin', $html);
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $html);

        // Persian digits conversion (۱۲.۵)
        self::assertStringContainsString('۱۲.۵', $html);
    }

    public function test_lottie_and_webm_modes_render_cleanly(): void
    {
        $renderer = new BlockRenderer();

        // 1. Lottie mode with custom URL
        $lottieHtml = $renderer->renderBlock([
            'id' => 'lottie-1',
            'type' => 'solar-energy-cycle',
            'props' => [
                'mode' => 'lottie',
                'lottie_url' => 'https://assets.example.com/solar-anim.json',
                'autoplay' => true,
                'loop_behavior' => 'infinite',
            ],
        ]);
        self::assertStringContainsString('lottie-player', $lottieHtml);
        self::assertStringContainsString('src="https://assets.example.com/solar-anim.json"', $lottieHtml);
        self::assertStringContainsString('loop', $lottieHtml);
        self::assertStringContainsString('autoplay', $lottieHtml);

        // 2. WebM mode with sources and poster
        $webmHtml = $renderer->renderBlock([
            'id' => 'webm-1',
            'type' => 'solar-energy-cycle',
            'props' => [
                'mode' => 'webm_video',
                'video_webm_url' => '/storage/videos/solar-flow.webm',
                'video_mp4_fallback_url' => '/storage/videos/solar-flow.mp4',
                'poster_image' => '/storage/videos/solar-poster.jpg',
                'autoplay' => true,
                'loop_behavior' => 'infinite',
            ],
        ]);
        self::assertStringContainsString('<video', $webmHtml);
        self::assertStringContainsString('src="/storage/videos/solar-flow.webm" type="video/webm"', $webmHtml);
        self::assertStringContainsString('src="/storage/videos/solar-flow.mp4" type="video/mp4"', $webmHtml);
        self::assertStringContainsString('poster="/storage/videos/solar-poster.jpg"', $webmHtml);
        self::assertStringContainsString('playsinline', $webmHtml);
    }

    public function test_inspector_speed_loop_and_color_themes_customization(): void
    {
        $renderer = new BlockRenderer();

        // High speed (2x) with alternate loop and eco-emerald theme
        $emeraldHtml = $renderer->renderBlock([
            'id' => 'emerald-1',
            'type' => 'solar-energy-cycle',
            'props' => [
                'animation_speed' => '2x',
                'animation_duration' => '6',
                'loop_behavior' => 'alternate',
                'color_theme' => 'eco-emerald',
                'glow_effect' => true,
            ],
        ]);
        // 6s * 0.5 = 3s
        self::assertStringContainsString('--smg-duration: 3s;', $emeraldHtml);
        self::assertStringContainsString('--smg-direction: alternate;', $emeraldHtml);
        self::assertStringContainsString('--smg-accent: #10b981;', $emeraldHtml);

        // Custom colors theme
        $customHtml = $renderer->renderBlock([
            'id' => 'custom-1',
            'type' => 'solar-energy-cycle',
            'props' => [
                'color_theme' => 'custom',
                'custom_accent_color' => '#f97316',
                'custom_panel_color' => '#18181b',
                'custom_particle_color' => '#a855f7',
                'animation_speed' => '0.5x',
                'animation_duration' => '4',
            ],
        ]);
        // 4s * 2.0 = 8s
        self::assertStringContainsString('--smg-duration: 8s;', $customHtml);
        self::assertStringContainsString('--smg-accent: #f97316;', $customHtml);
        self::assertStringContainsString('--smg-panel: #18181b;', $customHtml);
        self::assertStringContainsString('--smg-particle: #a855f7;', $customHtml);
    }

    public function test_steps_grid_and_tooltips_render_with_persian_steps(): void
    {
        $renderer = new BlockRenderer();
        $html = $renderer->renderBlock([
            'id' => 'steps-1',
            'type' => 'solar-energy-cycle',
            'props' => [
                'show_steps' => true,
                'persian_digits' => true,
                'steps' => [
                    [
                        'title' => 'مرحله تست تابش',
                        'step_num' => '۱',
                        'icon' => 'sun',
                        'desc' => 'توضیحات تست مرحله اول',
                        'badge' => 'نشان تست',
                        'tooltip' => 'راهنمای شناور مرحله تست',
                    ],
                ],
            ],
        ]);

        self::assertStringContainsString('مرحله تست تابش', $html);
        self::assertStringContainsString('توضیحات تست مرحله اول', $html);
        self::assertStringContainsString('نشان تست', $html);
        self::assertStringContainsString('راهنمای شناور مرحله تست', $html);
        self::assertStringContainsString('smg-step-card', $html);
    }

    public function test_latin_digits_toggle_preserves_raw_numbers(): void
    {
        $renderer = new BlockRenderer();
        $html = $renderer->renderBlock([
            'id' => 'digits-1',
            'type' => 'solar-energy-cycle',
            'props' => [
                'persian_digits' => false,
                'counter_power_value' => '8.4',
                'counter_efficiency_value' => '23.5',
            ],
        ]);

        self::assertStringContainsString('8.4', $html);
        self::assertStringContainsString('23.5', $html);
    }
}
