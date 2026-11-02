<?php

namespace Tests\Unit\ThemeBuilder;

use App\Console\Commands\SentinelProfilesCommand;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\Widget\WidgetFeatureProfile;
use SaaSPlatform\ThemeRuntime\Widget\WidgetFrictionProfile;
use Tests\TestCase;

/**
 * ADR-077: the Sentinel UI verifier's widget profiles name only real
 * registry types and declared slots, and the committed
 * tests/sentinel/widget-profiles.json is what `sentinel:profiles` writes.
 */
class Adr077SentinelProfilesTest extends TestCase
{
    public function test_profiles_name_only_registry_types_and_declared_slots(): void
    {
        $this->assertSame([], WidgetFeatureProfile::violations(BlockRegistry::all()));
    }

    public function test_violations_catch_unknown_types_slots_and_severities(): void
    {
        $definitions = ['w' => ['name' => 'W', 'category' => 'content', 'icon' => 'box', 'schema' => [], 'defaultProps' => [], 'slots' => ['body']]];
        $this->assertSame([], WidgetFeatureProfile::violations($definitions + BlockRegistry::all()));
        $this->assertContains('nav_header: not a registry type', WidgetFeatureProfile::violations($definitions));
    }

    public function test_the_committed_profiles_file_is_current(): void
    {
        $this->assertFileExists(SentinelProfilesCommand::path());
        $this->assertSame(SentinelProfilesCommand::render(), file_get_contents(SentinelProfilesCommand::path()), 'run php artisan sentinel:profiles');
        $this->artisan('sentinel:profiles', ['--check' => true])->assertExitCode(0);
    }

    public function test_the_export_carries_header_and_footer_requirements(): void
    {
        $export = WidgetFeatureProfile::export(BlockRegistry::all());
        $this->assertSame(WidgetFeatureProfile::SCHEMA, $export['schema']);
        $this->assertSame(['logo', 'menu', 'actions', 'quick-access'], $export['slots']['nav_header']);
        $codes = array_column($export['profiles']['nav_header'], 'code');
        $this->assertSame(['FEATURE_MISSING_MENU', 'FEATURE_EMPTY_MENU', 'FEATURE_MISSING_LOGO', 'FEATURE_MISSING_ACTIONS'], $codes);
        $this->assertSame('DEAD_LINK', $export['global'][0]['code']);
        foreach ($export['profiles'] as $type => $rules) {
            foreach ($rules as $rule) {
                $this->assertNotSame('', trim($rule['remedy']), "{$type}/{$rule['code']} has a task text");
            }
        }
    }

    public function test_friction_flags_carousels_without_drag_dots_or_arrows_controls(): void
    {
        $friction = WidgetFrictionProfile::analyze(BlockRegistry::all());
        foreach (['slider', 'product_slider', 'category_slider', 'testimonials_slider', 'blog_posts'] as $type) {
            $this->assertArrayHasKey($type, $friction, $type);
            $this->assertSame('carousel', $friction[$type]['kind']);
            $this->assertSame(['drag', 'dots', 'arrows'], array_column($friction[$type]['missing'], 'control'), $type);
            $this->assertSame(['responsive_padding', 'radius'], $friction[$type]['provided_by_style_panel'], 'Style-tab controls are never reported as missing');
        }
        $this->assertArrayNotHasKey('video', $friction, 'autoplay alone does not make a carousel');
        foreach (array_keys($friction) as $type) {
            $this->assertNotContains(BlockRegistry::all()[$type]['category'] ?? null, ['foodmart', 'sleepshiman'], 'locked theme-pack blocks are exempt');
        }
        $complete = ['schema' => ['autoplay' => [], 'interval' => [], 'drag' => [], 'dots' => [], 'arrows' => []], 'category' => 'content'];
        $this->assertSame([], WidgetFrictionProfile::analyze(['x_slider' => $complete]));
        $this->assertSame(WidgetFrictionProfile::analyze(BlockRegistry::all()), WidgetFeatureProfile::export(BlockRegistry::all())['friction']);
    }

    public function test_registry_defaults_point_at_real_pages_and_the_phone_link_is_a_44px_target(): void
    {
        $all = BlockRegistry::all();
        $this->assertSame('/contact', $all['header_modern_v1']['defaultProps']['ctaUrl']);
        $this->assertSame('/contact', $all['header_modern_v1']['schema']['ctaUrl']['default']);
        $this->assertSame('/contact', $all['nav_header']['schema']['cta_url']['default']);
        $footer = (new \App\Services\ThemeBuilder\BlockRenderer())->renderBlock(['type' => 'nav_footer', 'id' => 'f1', 'props' => $all['nav_footer']['defaultProps']]);
        $this->assertStringContainsString('<li><a href="/contact">تماس با ما</a></li>', $footer);
        $this->assertStringNotContainsString('href="#"', $footer);

        $css = file_get_contents(base_path('packages/saas-platform/modules/theme-runtime/resources/css/theme-runtime-blocks.css'));
        $this->assertStringContainsString('@media (max-width: 767.98px) { .tb-nav-header-phone { display: inline-flex; align-items: center; min-block-size: 2.75rem;', $css);
    }

    public function test_the_suite_is_read_only_and_driven_by_base_url(): void
    {
        $config = file_get_contents(base_path('tests/sentinel/playwright.sentinel.config.ts'));
        $env = file_get_contents(base_path('tests/sentinel/support/env.ts'));
        $this->assertStringContainsString("updateSnapshots: env.updateBaselines ? 'all' : 'none'", $config);
        $this->assertStringContainsString('process.env.BASE_URL', $env);
        foreach (glob(base_path('tests/sentinel/*.sentinel.ts')) as $spec) {
            $code = file_get_contents($spec);
            // `.type(` with an argument types into a field; `message.type()` only reads.
            $this->assertDoesNotMatchRegularExpression('/\.(click|dblclick|fill|press|check|uncheck|selectOption|setInputFiles|dragTo)\(|\.type\(\s*[^)\s]|method:\s*[\'"](POST|PUT|PATCH|DELETE)/i', $code, basename($spec) . ' must only read');
        }
    }
}
