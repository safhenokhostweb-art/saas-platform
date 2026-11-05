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

    public function test_header_ctas_without_a_destination_stay_off_the_site_but_show_in_the_editor(): void
    {
        $cases = [
            // The closing quote matches the class attribute, not header_modern_v1's inline CSS.
            'header_modern_v1' => [['ctaText' => 'شروع', 'ctaUrl' => '#'], ['ctaText' => 'شروع', 'ctaUrl' => '/contact'], 'tb-header-modern__cta"'],
        ];
        foreach ($cases as $type => [$dead, $live, $class]) {
            $renderer = new \App\Services\ThemeBuilder\BlockRenderer();
            $site = $renderer->renderBlock(['type' => $type, 'id' => 'h1', 'props' => $dead]);
            $this->assertStringNotContainsString($class, $site, $type);
            $this->assertStringNotContainsString('href="#"', $site, $type);
            $this->assertStringContainsString('href="/contact"', $renderer->renderBlock(['type' => $type, 'id' => 'h1', 'props' => $live]), $type);

            $renderer->setContext(['editor_mode' => true]);
            $editor = $renderer->renderBlock(['type' => $type, 'id' => 'h1', 'props' => $dead]);
            $this->assertStringContainsString($class, $editor, $type);
            $this->assertStringContainsString('data-tb-dead-link', $editor, $type);
            $this->assertStringNotContainsString('data-tb-dead-link', $renderer->renderBlock(['type' => $type, 'id' => 'h1', 'props' => $live]), $type);
        }
    }

    /**
     * nav_header (640304d0, owner decision): a CTA with text but no
     * destination stays visible as a stub on the site too, so the site
     * manager can see it, and is still flagged as a dead link.
     */
    public function test_nav_header_cta_without_a_destination_renders_as_a_flagged_stub(): void
    {
        $dead = ['cta_text' => 'شروع', 'cta_url' => ''];
        $live = ['cta_text' => 'شروع', 'cta_url' => '/contact'];

        foreach ([[], ['editor_mode' => true]] as $context) {
            $renderer = new \App\Services\ThemeBuilder\BlockRenderer();
            if ($context !== []) {
                $renderer->setContext($context);
            }
            $label = $context === [] ? 'site' : 'editor';

            $stub = $renderer->renderBlock(['type' => 'nav_header', 'id' => 'h1', 'props' => $dead]);
            $this->assertStringContainsString('<a href="#" class="tb-btn tb-btn-primary tb-nav-header-cta"', $stub, $label);
            $this->assertStringContainsString('data-tb-dead-link', $stub, $label);

            $real = $renderer->renderBlock(['type' => 'nav_header', 'id' => 'h1', 'props' => $live]);
            $this->assertStringContainsString('<a href="/contact" class="tb-btn tb-btn-primary tb-nav-header-cta"', $real, $label);
            $this->assertStringNotContainsString('data-tb-dead-link', $real, $label);
        }

        // No CTA text means no CTA, whatever the destination.
        $none = (new \App\Services\ThemeBuilder\BlockRenderer())->renderBlock(['type' => 'nav_header', 'id' => 'h1', 'props' => ['cta_text' => '', 'cta_url' => '/contact']]);
        $this->assertStringNotContainsString('tb-nav-header-cta"', $none);
    }

    public function test_the_image_logo_shrinks_so_a_long_brand_name_cannot_widen_the_page(): void
    {
        $css = file_get_contents(base_path('packages/saas-platform/modules/theme-runtime/resources/css/theme-runtime-blocks.css'));
        $this->assertStringContainsString('.tb-nav-header-logo--image { display: inline-flex; align-items: center; gap: 0.6rem; flex: 0 1 auto; min-width: 0; max-width: 100%; }', $css);
        $this->assertStringContainsString('.tb-nav-header-logo-text { line-height: 1.2; min-width: 0; overflow-wrap: anywhere; }', $css);
        $this->assertDoesNotMatchRegularExpression('/\.tb-nav-header-logo--image \{[^}]*flex: none/', $css);
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
