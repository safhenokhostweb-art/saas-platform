<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use Tests\TestCase;

/**
 * Phase 0.2c (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md, ADR-062) —
 * the canonical side of the shadowed-type harmonization: customer-site's
 * 65 local copies are gone, so these canonical definitions are what both
 * Canvas editors author. customer-site pins the host side
 * (CanonicalBlockDefinitionParityTest).
 */
class Phase02cCanonicalHarmonizationTest extends TestCase
{
    /** Top-level field types the platform Canvas content tab has a control for. */
    private const EDITABLE_TYPES = [
        'string', 'text', 'textarea', 'rich_text', 'link', 'image', 'repeater', 'array',
        'number', 'motion', 'boolean', 'select', 'color',
    ];

    private function render(string $type, array $props): string
    {
        return (new BlockRenderer())->renderBlock(['type' => $type, 'id' => 'b1', 'props' => $props]);
    }

    private function field(string $type, string $field): array
    {
        return BlockRegistry::all()[$type]['schema'][$field] ?? [];
    }

    public function test_every_field_has_a_canvas_control_and_a_label(): void
    {
        $missing = [];
        $unlabelled = [];
        foreach (BlockRegistry::all() as $type => $def) {
            foreach ($def['schema'] ?? [] as $key => $field) {
                $kind = $field['type'] ?? null;
                if ($kind === 'blocks') {
                    continue; // a container's child list — authored in the children panel
                }
                if (!in_array($kind, self::EDITABLE_TYPES, true)) {
                    $missing[] = "{$type}.{$key} ({$kind})";
                }
                if ($kind === 'select') {
                    $this->assertNotEmpty($field['options'] ?? [], "{$type}.{$key} is a select without options.");
                }
            }
        }
        $this->assertSame([], $missing, 'Fields no Canvas control renders: ' . implode(', ', $missing));

        // Every type the customer-site Canvas used to shadow now carries a
        // label on every field, so neither editor shows a raw prop key.
        foreach (['hero', 'section', 'grid', 'columns', 'heading', 'text', 'video', 'button', 'counter', 'blog_posts',
                  'testimonials', 'product_grid', 'product_slider', 'cta', 'countdown', 'promo_banner', 'search_bar',
                  'nav_menu', 'social_icons', 'accordion', 'gallery', 'slider'] as $type) {
            foreach (BlockRegistry::all()[$type]['schema'] as $key => $field) {
                if (($field['type'] ?? null) !== 'blocks' && trim((string) ($field['label'] ?? '')) === '') {
                    $unlabelled[] = "{$type}.{$key}";
                }
            }
        }
        $this->assertSame([], $unlabelled, 'Unlabelled fields: ' . implode(', ', $unlabelled));
    }

    public function test_spacing_tokens_are_selects_over_the_renderer_scale(): void
    {
        foreach (['section.padding', 'grid.gap', 'columns.gap'] as $path) {
            [$type, $key] = explode('.', $path);
            $field = $this->field($type, $key);
            $this->assertSame('select', $field['type'], $path);
            $this->assertSame(['none', 'xs', 'sm', 'md', 'lg', 'xl'], array_keys($field['options']), $path);
        }
    }

    public function test_option_lists_offer_only_what_the_renderer_honours(): void
    {
        $this->assertSame([1, 2, 3, 4, 5, 6], array_keys($this->field('heading', 'level')['options']));
        $this->assertSame(['primary', 'secondary'], array_keys($this->field('button', 'style')['options']));
        $this->assertSame(['info', 'sale', 'urgent'], array_keys($this->field('promo_banner', 'style')['options']));
        $this->assertSame(['header', 'footer'], array_keys($this->field('nav_menu', 'location')['options']));
        $this->assertSame(['right', 'center', 'left'], array_keys($this->field('image', 'align')['options']));
        $this->assertSame(['cards', 'minimal'], array_keys($this->field('module:reviews_grid', 'style')['options']));
    }

    public function test_fields_no_renderer_reads_are_gone(): void
    {
        $all = BlockRegistry::all();
        $this->assertArrayNotHasKey('show_instructor', $all['module:courses_grid']['schema']);
        $this->assertArrayNotHasKey('show_instructor', $all['module:courses_grid']['defaultProps']);
        foreach (['highlight_plan', 'show_annual_toggle'] as $key) {
            $this->assertArrayNotHasKey($key, $all['module:pricing_table']['schema']);
            $this->assertArrayNotHasKey($key, $all['module:pricing_table']['defaultProps']);
        }
    }

    public function test_promoted_host_fields_are_canonical(): void
    {
        $video = BlockRegistry::all()['video'];
        $this->assertSame(['url', 'poster', 'caption', 'autoplay', 'loop', 'controls'], array_keys($video['schema']));
        $this->assertSame('video', $video['schema']['url']['media_kind']);
        $this->assertSame('image', $video['schema']['url']['type']);

        $blog = BlockRegistry::all()['blog_posts']['schema'];
        foreach (['layout', 'source', 'category_id', 'manual_ids', 'autoplay', 'interval'] as $key) {
            $this->assertArrayHasKey($key, $blog);
        }

        $social = BlockRegistry::all()['social_icons']['schema'];
        $this->assertSame('repeater', $social['links']['type']);
        $this->assertSame('array', $social['links']['storage']);
        $this->assertSame('link', $social['links']['fields']['url']['type']);
        $this->assertSame(['icons', 'buttons', 'text'], array_keys($social['style']['options']));

        $this->assertSame('color', $this->field('cta', 'background')['type']);
        $this->assertSame('motion', $this->field('module:motion', 'motion_asset_id')['type']);
        $this->assertSame('image', $this->field('module:motion', 'fallback_image')['type']);
    }

    public function test_cta_background_is_escaped_and_a_custom_colour_is_applied(): void
    {
        $token = $this->render('cta', ['title' => 'T', 'background' => 'secondary']);
        $this->assertStringContainsString('class="tb-cta tb-bg-secondary"', $token);
        $this->assertStringNotContainsString('style="background', $token);

        $hex = $this->render('cta', ['title' => 'T', 'background' => '#0ea5e9']);
        $this->assertStringContainsString('class="tb-cta tb-bg-custom"', $hex);
        $this->assertMatchesRegularExpression('/style="background:#0ea5e9;color:#[0-9a-f]{6}"/i', $hex);

        $var = $this->render('cta', ['title' => 'T', 'background' => 'var(--tb-primary)']);
        $this->assertStringContainsString('style="background:var(--tb-primary)"', $var);

        $evil = $this->render('cta', ['title' => 'T', 'background' => 'x" onmouseover="alert(1)']);
        $this->assertStringNotContainsString('onmouseover', $evil);
        $this->assertStringContainsString('class="tb-cta tb-bg-primary"', $evil);
    }

    public function test_nav_menu_location_is_allowlisted(): void
    {
        $this->assertStringContainsString('class="tb-nav tb-nav-footer"', $this->render('nav_menu', ['location' => 'footer']));
        $evil = $this->render('nav_menu', ['location' => 'x" onclick="alert(1)']);
        $this->assertStringNotContainsString('onclick', $evil);
        $this->assertStringContainsString('class="tb-nav tb-nav-header"', $evil);
    }

    public function test_video_refuses_unsafe_urls_and_reads_strict_switches(): void
    {
        $this->assertStringContainsString('tb-empty-state', $this->render('video', ['url' => 'javascript:alert(1)']));
        // An unresolved media reference is "no video", not an error.
        $this->assertStringContainsString('tb-empty-state', $this->render('video', ['url' => ['media_id' => 'x']]));

        $html = $this->render('video', [
            'url' => '/media/intro.mp4', 'poster' => 'javascript:alert(1)',
            'autoplay' => 'false', 'loop' => '0', 'controls' => true,
        ]);
        $this->assertStringContainsString('src="/media/intro.mp4"', $html);
        $this->assertStringNotContainsString('poster=', $html);
        $this->assertStringNotContainsString('autoplay', $html);
        $this->assertStringNotContainsString(' loop', $html);
        $this->assertStringContainsString('controls', $html);
    }

    public function test_social_icons_style_variants_and_unstyled_markup(): void
    {
        $links = [['label' => 'اینستاگرام', 'url' => 'https://instagram.com/x'], ['label' => 'تلگرام', 'url' => 'javascript:alert(1)']];

        // Unauthored style keeps the original canonical markup.
        $plain = $this->render('social_icons', ['links' => $links]);
        $this->assertStringContainsString('<div id="tb-b1" class="tb-social-icons">', $plain);
        $this->assertStringContainsString('>اینستاگرام</a>', $plain);

        $icons = $this->render('social_icons', ['links' => $links, 'style' => 'icons']);
        $this->assertStringContainsString('class="tb-social-icons tb-social-icons-icons"', $icons);
        $this->assertStringContainsString('aria-label="اینستاگرام">ا</a>', $icons);
        $this->assertStringContainsString('href="#"', $icons); // the javascript: link is refused

        $buttons = $this->render('social_icons', ['links' => $links, 'style' => 'buttons']);
        $this->assertStringContainsString('class="tb-btn tb-btn-secondary"', $buttons);

        $unknown = $this->render('social_icons', ['links' => $links, 'style' => '"><script>']);
        $this->assertStringContainsString('tb-social-icons-icons', $unknown);
        $this->assertStringNotContainsString('<script>', $unknown);
    }
}
