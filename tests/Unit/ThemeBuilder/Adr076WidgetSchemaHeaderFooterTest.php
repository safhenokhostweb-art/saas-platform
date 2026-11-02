<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\BlockRenderer as SharedBlockRenderer;
use SaaSPlatform\ThemeRuntime\Support\SocialNetworks;
use SaaSPlatform\ThemeRuntime\Widget\WidgetSchemaContract;
use Tests\TestCase;

/**
 * ADR-076: the schema contract every Canvas widget meets, the header's logo
 * image / menu placement / quick-access icons, the native messenger marks in
 * header and footer, the data-widget-* hooks and the shared page-tab strip.
 */
class Adr076WidgetSchemaHeaderFooterTest extends TestCase
{
    private const RUNTIME = 'packages/saas-platform/modules/theme-runtime';
    private const NATIVE_MESSENGERS = ['eitaa', 'bale', 'rubika', 'soroush', 'gap'];

    private function render(string $type, array $props, ?string $mode = null, array $context = []): string
    {
        $r = new BlockRenderer();
        if ($context !== []) {
            $r->setContext($context);
        }
        if ($mode !== null) {
            $r->setMode($mode);
        }
        return $r->renderBlock(['type' => $type, 'id' => 'b1', 'props' => $props]);
    }

    // ── Schema contract ───────────────────────────────────────────────

    public function test_every_registry_widget_meets_the_schema_contract(): void
    {
        $this->assertSame([], WidgetSchemaContract::violations(BlockRegistry::all()));
    }

    public function test_the_contract_names_each_kind_of_drift(): void
    {
        $base = ['name' => 'W', 'category' => 'content', 'icon' => 'box', 'defaultProps' => []];
        $cases = [
            'w: unknown category' => ['category' => 'commerce'] + $base,
            'w.a: default \'x\' is not an option' => $base + ['schema' => ['a' => ['type' => 'select', 'label' => 'A', 'options' => ['y' => 'Y'], 'default' => 'x']]],
            'w.a: ui \'segmented\' is not rendered for string controls' => $base + ['schema' => ['a' => ['type' => 'string', 'label' => 'A', 'ui' => 'segmented']]],
            'w.a: item_schema is not read by any editor' => $base + ['schema' => ['a' => ['type' => 'repeater', 'label' => 'A', 'fields' => ['b' => ['type' => 'string', 'label' => 'B']], 'item_schema' => []]]],
            'w.a: repeater needs fields' => $base + ['schema' => ['a' => ['type' => 'repeater', 'label' => 'A']]],
            'w.a[].b: group applies to top-level fields only' => $base + ['schema' => ['a' => ['type' => 'repeater', 'label' => 'A', 'fields' => ['b' => ['type' => 'string', 'label' => 'B', 'group' => 'G']]]]],
            'w.a: missing label' => $base + ['schema' => ['a' => ['type' => 'string']]],
            'w.a: default is outside min/max' => $base + ['schema' => ['a' => ['type' => 'number', 'label' => 'A', 'default' => 5, 'min' => 10]]],
            'w.z: default prop has no schema field' => ['defaultProps' => ['z' => 1]] + $base + ['schema' => []],
            'w: slot \'Bad Slot\' is not a slug' => $base + ['schema' => [], 'slots' => ['Bad Slot']],
        ];
        foreach ($cases as $expected => $definition) {
            $found = implode("\n", WidgetSchemaContract::definitionViolations('w', $definition));
            $this->assertStringContainsString($expected, $found, "expected «{$expected}»");
        }
        $this->assertSame(['W X: id is not a stable slug'], WidgetSchemaContract::definitionViolations('W X', $base + ['schema' => []]));
    }

    public function test_the_manifest_publishes_slots_and_controls_for_tests_and_sentinel(): void
    {
        $manifest = WidgetSchemaContract::manifest(BlockRegistry::all());
        $this->assertSame(['logo', 'menu', 'actions', 'quick-access'], $manifest['nav_header']['slots']);
        $this->assertSame(['brand', 'columns', 'bottom', 'social'], $manifest['nav_footer']['slots']);
        $this->assertSame('image', $manifest['nav_header']['fields']['logo_image']);
        $this->assertSame('select', $manifest['nav_header']['fields']['menu_align']);
        $this->assertSame('repeater', $manifest['nav_header']['fields']['social_links']);
        $this->assertSame('utility', $manifest['nav_header']['category']);
    }

    // ── Header ────────────────────────────────────────────────────────

    public function test_the_header_declares_logo_alignment_and_quick_access_in_its_schema(): void
    {
        $schema = BlockRegistry::all()['nav_header']['schema'];
        $this->assertSame('image', $schema['logo_image']['type']);
        $this->assertSame([40, 20, 120], [$schema['logo_height']['default'], $schema['logo_height']['min'], $schema['logo_height']['max']]);
        $this->assertSame([32, 16, 96], [$schema['logo_height_mobile']['default'], $schema['logo_height_mobile']['min'], $schema['logo_height_mobile']['max']]);
        $this->assertSame(['start', 'center', 'end'], array_keys($schema['menu_align']['options']));
        $this->assertSame(['end', 'segmented'], [$schema['menu_align']['default'], $schema['menu_align']['ui']]);
        $this->assertSame(['color', 'color'], [$schema['bg_color']['type'], $schema['text_color']['type']], 'real colour controls');
        foreach (['nav_header', 'nav_footer'] as $type) {
            $network = BlockRegistry::all()[$type]['schema']['social_links']['fields']['network'];
            $this->assertSame(['auto', ...SocialNetworks::keys()], array_keys($network['options']), $type);
        }
        // Every field sits in a group and each group is contiguous, so it
        // opens exactly one sidebar section.
        $runs = [];
        foreach ($schema as $key => $field) {
            $this->assertArrayHasKey('group', $field, $key);
            if (end($runs) !== $field['group']) {
                $runs[] = $field['group'];
            }
        }
        $this->assertSame(array_values(array_unique($runs)), $runs);
    }

    public function test_the_logo_image_renders_with_clamped_heights_alt_text_and_brand_name(): void
    {
        $html = $this->render('nav_header', ['logo_image' => '/storage/brand/logo.webp', 'logo_text' => 'فروشگاه نمونه', 'logo_height' => 500, 'logo_height_mobile' => 'x', 'logo_url' => '/']);
        $this->assertStringContainsString('class="tb-nav-header-logo tb-nav-header-logo--image" style="--tb-logo-h:120px;--tb-logo-h-m:32px" data-widget-slot="logo"', $html);
        $this->assertStringContainsString('<img src="/storage/brand/logo.webp" alt="فروشگاه نمونه" class="tb-nav-header-logo-img" height="120" decoding="async">', $html);
        $this->assertStringContainsString('<span class="tb-nav-header-logo-text">فروشگاه نمونه</span>', $html);

        $alt = $this->render('nav_header', ['logo_image' => '/l.png', 'logo_alt' => 'لوگوی "ما"', 'logo_height' => 4]);
        $this->assertStringContainsString('alt="لوگوی &quot;ما&quot;"', $alt);
        $this->assertStringContainsString('--tb-logo-h:20px', $alt);
        $this->assertStringNotContainsString('tb-nav-header-logo-text', $alt);
    }

    public function test_an_unsafe_or_unresolved_logo_image_falls_back_to_the_text_logo(): void
    {
        foreach (['javascript:alert(1)', ['media_id' => 'abc'], ''] as $value) {
            $html = $this->render('nav_header', ['logo_image' => $value, 'logo_text' => 'برند']);
            $this->assertStringNotContainsString('<img', $html);
            $this->assertStringContainsString('class="tb-nav-header-logo" data-widget-slot="logo">برند</a>', $html);
        }
        $site = $this->render('nav_header', [], null, ['site_logo_html' => '<a class="site-logo">S</a>']);
        $this->assertStringContainsString('<a class="site-logo">S</a>', $site, 'no image and no text keeps the global logo (BLD-25)');
    }

    public function test_menu_alignment_adds_a_class_only_off_the_original_layout(): void
    {
        $this->assertStringContainsString('<div id="tb-b1" class="tb-nav-header" style=', $this->render('nav_header', ['logo_text' => 'L']));
        $this->assertStringContainsString('<div id="tb-b1" class="tb-nav-header" style=', $this->render('nav_header', ['menu_align' => 'end']));
        $this->assertStringContainsString('class="tb-nav-header tb-nav-header--menu-start"', $this->render('nav_header', ['menu_align' => 'start']));
        $this->assertStringContainsString('class="tb-nav-header tb-nav-header--menu-center"', $this->render('nav_header', ['menu_align' => 'center']));
        $this->assertStringContainsString('<div id="tb-b1" class="tb-nav-header" style=', $this->render('nav_header', ['menu_align' => '"><script>']));

        $css = file_get_contents(base_path(self::RUNTIME . '/resources/css/theme-runtime-blocks.css'));
        $this->assertStringContainsString('.tb-nav-header--menu-start .tb-nav-header-links { margin-inline-start: 0; margin-inline-end: auto; }', $css);
        $this->assertStringContainsString('.tb-nav-header--menu-center .tb-nav-header-inner { display: grid;', $css);
    }

    public function test_quick_access_icons_render_in_the_actions_slot_before_the_cta(): void
    {
        $html = $this->render('nav_header', [
            'cta_text' => 'تماس', 'cta_url' => '/contact',
            'social_links' => [['url' => 'https://eitaa.com/shop'], ['url' => 'https://ble.ir/shop', 'network' => 'auto'], ['url' => 'javascript:alert(1)', 'network' => 'telegram']],
            'social_style' => 'brand', 'social_show_mobile' => true,
        ]);
        $this->assertStringContainsString('<div class="tb-nav-header-actions" data-widget-slot="actions">', $html);
        $this->assertStringContainsString('<div id="tb-b1-quick" class="tb-social-icons tb-social-icons--svg tb-social-icons--brand tb-nav-header-social tb-nav-header-social--mobile" data-widget-slot="quick-access">', $html);
        $this->assertStringContainsString('tb-social-icon--eitaa" target="_blank" rel="noopener noreferrer" aria-label="ایتا"', $html);
        $this->assertStringContainsString('tb-social-icon--bale"', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertLessThan(strpos($html, 'tb-nav-header-cta'), strpos($html, 'data-widget-slot="quick-access"'));
        $this->assertStringContainsString('<nav class="tb-nav-header-links" aria-label="منوی اصلی" data-widget-slot="menu">', $html);

        $plain = $this->render('nav_header', ['social_links' => [['url' => 'https://t.me/x']]]);
        $this->assertStringContainsString('class="tb-social-icons tb-social-icons--svg tb-nav-header-social" data-widget-slot="quick-access"', $plain, 'mono, hidden on phones by default');
        $this->assertStringNotContainsString('quick-access', $this->render('nav_header', ['logo_text' => 'L']));
    }

    // ── Social networks ───────────────────────────────────────────────

    public function test_native_messengers_have_vector_marks_and_url_detection(): void
    {
        $urls = ['eitaa' => 'https://eitaa.com/x', 'bale' => 'https://ble.ir/x', 'rubika' => 'https://rubika.ir/x', 'soroush' => 'https://splus.ir/x', 'gap' => 'https://gap.im/x'];
        foreach (self::NATIVE_MESSENGERS as $network) {
            $svg = SocialNetworks::svg($network);
            $this->assertStringContainsString("tb-icon-svg-mark-{$network}", $svg, $network);
            $this->assertStringContainsString('aria-hidden="true"', $svg);
            $this->assertMatchesRegularExpression('/<(path|rect)\b/', $svg);
            $this->assertSame($network, SocialNetworks::detect($urls[$network]));
            $this->assertNotSame('', SocialNetworks::label($network));
        }
        $this->assertSame('soroush', SocialNetworks::detect('https://sapp.ir/channel'));
        $this->assertNull(SocialNetworks::detect('https://gapfiller.com/'), 'gap.im only');
        $this->assertSame('', SocialNetworks::svg('myspace'));
        $marks = json_decode(file_get_contents(SocialNetworks::marksPath()), true);
        foreach ($marks['marks'] as $key => $mark) {
            $this->assertDoesNotMatchRegularExpression('/<script|on[a-z]+=|href=/i', $mark['body'], $key);
        }
    }

    public function test_every_brand_fill_has_css_and_keeps_a_white_glyph_at_aa_contrast(): void
    {
        $css = file_get_contents(base_path(self::RUNTIME . '/resources/css/theme-runtime-blocks.css'));
        foreach (SocialNetworks::keys() as $network) {
            $brand = SocialNetworks::brandColor($network);
            if ($brand === '') {
                $this->assertContains($network, ['email', 'phone']);
                continue;
            }
            $this->assertStringContainsString(".tb-social-icon--{$network} { --tb-social-brand: {$brand}; }", $css, $network);
            $this->assertGreaterThanOrEqual(4.0, self::contrastWithWhite($brand), $network);
        }
    }

    public function test_the_footer_renders_every_native_messenger_with_slots_and_brand_style(): void
    {
        $links = array_map(fn ($n) => ['url' => "https://example.ir/{$n}", 'network' => $n], self::NATIVE_MESSENGERS);
        $html = $this->render('nav_footer', ['logo_text' => 'برند', 'copyright' => '©', 'social_links' => $links, 'social_style' => 'brand',
            'columns' => [['title' => 'خدمات', 'links' => [['label' => 'ارسال', 'url' => '/shipping']]]]]);
        $this->assertStringContainsString('<div id="tb-b1-social" class="tb-social-icons tb-social-icons--svg tb-social-icons--brand" data-widget-slot="social">', $html);
        foreach (self::NATIVE_MESSENGERS as $network) {
            $this->assertStringContainsString("tb-social-icon--{$network}\"", $html);
            $this->assertStringContainsString("tb-icon-svg-mark-{$network}", $html);
        }
        $this->assertStringContainsString('class="tb-nav-footer-brand" data-widget-slot="brand"', $html);
        $this->assertStringContainsString('class="tb-nav-footer-cols" data-widget-slot="columns"', $html);
        $this->assertStringContainsString('class="tb-nav-footer-bottom" data-widget-slot="bottom"', $html);
        $mono = $this->render('nav_footer', ['social_links' => [['url' => 'https://gap.im/x']]]);
        $this->assertStringContainsString('class="tb-social-icons tb-social-icons--svg" data-widget-slot="social"', $mono);
    }

    // ── Hooks and editors ─────────────────────────────────────────────

    public function test_block_wrappers_carry_data_widget_type_in_public_and_editor_modes(): void
    {
        $public = $this->render('heading', ['text' => 'سلام'], SharedBlockRenderer::MODE_PUBLIC);
        $this->assertStringContainsString('data-tb-block-type="heading" data-widget-type="heading"', $public);
        $editor = $this->render('heading', ['text' => 'سلام'], null, ['editor_mode' => true]);
        $this->assertStringContainsString('class="tb-canvas-block" data-tb-block-id="b1" data-tb-block-type="heading" data-widget-type="heading"', $editor);
    }

    public function test_the_platform_editor_uses_the_shared_tab_strip_and_schema_controls(): void
    {
        $canvas = file_get_contents(resource_path('views/admin/theme-builder/canvas.blade.php'));
        $this->assertStringContainsString("@include('theme-runtime::canvas.tab-strip')", $canvas);
        $this->assertStringContainsString('<div class="tbts" data-tb-tabstrip data-testid="tb-page-tabs">', $canvas);
        $this->assertStringContainsString(':data-canvas-page-tab="key"', $canvas);
        $this->assertSame(2, substr_count($canvas, "@include('theme-runtime::canvas.tab-strip-nav'"));
        $this->assertStringNotContainsString('overflow-x-auto flex-none', $canvas, 'the old strip grew past the toolbar');
        $this->assertStringContainsString("schemaDef.type === 'select' && schemaDef.ui === 'segmented'", $canvas);
        $this->assertStringContainsString('fieldGroupStart(propKey)', $canvas);

        $view = $this->app['view']->make('theme-runtime::canvas.tab-strip')->render();
        $this->assertStringContainsString('root.TbTabStrip = {', $view);
        $this->assertStringContainsString('.tbts-viewport', $view);
        $nav = $this->app['view']->make('theme-runtime::canvas.tab-strip-nav', ['dir' => 'end'])->render();
        $this->assertStringContainsString('data-tbts-dir="end" tabindex="-1" hidden', $nav);
    }

    private static function contrastWithWhite(string $hex): float
    {
        $channel = static function (int $v): float {
            $c = $v / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        [$r, $g, $b] = array_map('hexdec', str_split(ltrim($hex, '#'), 2));
        $l = 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
        return 1.05 / ($l + 0.05);
    }
}
