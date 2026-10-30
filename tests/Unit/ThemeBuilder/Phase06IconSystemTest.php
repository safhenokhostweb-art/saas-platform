<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\Inspector\InspectorFieldRegistry;
use SaaSPlatform\ThemeRuntime\Support\IconLibrary;
use Tests\TestCase;

/**
 * Phase 0.6 (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md, ADR-065): one
 * vendored SVG icon catalog (a curated Lucide subset) feeds the renderer
 * and both Canvas editors' icon picker; a legacy emoji/text icon value
 * keeps rendering exactly as before.
 */
class Phase06IconSystemTest extends TestCase
{
    private const RUNTIME = 'packages/saas-platform/modules/theme-runtime';

    private const ELEMENTS = [
        'path' => ['d'],
        'circle' => ['cx', 'cy', 'r'],
        'ellipse' => ['cx', 'cy', 'rx', 'ry'],
        'rect' => ['x', 'y', 'width', 'height', 'rx', 'ry'],
        'line' => ['x1', 'y1', 'x2', 'y2'],
        'polyline' => ['points'],
        'polygon' => ['points'],
    ];

    private function render(string $type, array $props): string
    {
        return (new BlockRenderer())->renderBlock(['type' => $type, 'id' => 'b1', 'props' => $props]);
    }

    public function test_the_catalog_is_licensed_categorised_and_labelled_in_persian(): void
    {
        $catalog = IconLibrary::catalog();
        $this->assertStringStartsWith('lucide-static@', $catalog['source']);
        $this->assertSame('ISC', $catalog['license']);
        $this->assertStringContainsString('ISC License', file_get_contents(base_path(self::RUNTIME . '/resources/icons/LICENSE-lucide.txt')));
        $this->assertSame(['commerce', 'navigation', 'communication', 'tech', 'ui'], array_column($catalog['categories'], 'key'));
        $this->assertGreaterThanOrEqual(100, count($catalog['icons']));

        foreach ($catalog['icons'] as $name => $icon) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $name);
            $this->assertContains($icon['category'], array_column($catalog['categories'], 'key'), $name);
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $icon['label'], "{$name} needs a Persian label");
            $this->assertNotEmpty($icon['keywords'], $name);
        }
    }

    /** The bodies are emitted unescaped, so every element and attribute is allowlisted. */
    public function test_every_icon_body_is_inert_svg_geometry(): void
    {
        foreach (IconLibrary::catalog()['icons'] as $name => $icon) {
            $rest = $icon['body'];
            $this->assertNotSame('', $rest, $name);
            while ($rest !== '') {
                $this->assertSame(1, preg_match('#^<([a-z]+)((?: [a-z0-9-]+="[^"<>&]*")*)/>#', $rest, $m), "{$name}: unexpected markup");
                $this->assertArrayHasKey($m[1], self::ELEMENTS, "{$name}: <{$m[1]}>");
                preg_match_all('# ([a-z0-9-]+)="([^"]*)"#', $m[2], $attrs, PREG_SET_ORDER);
                foreach ($attrs as [, $attr, $value]) {
                    if ($attr === 'fill') {
                        $this->assertContains($value, ['currentColor', 'none'], $name);
                        continue;
                    }
                    $this->assertContains($attr, self::ELEMENTS[$m[1]], "{$name}: {$attr} on <{$m[1]}>");
                    $this->assertMatchesRegularExpression('/^[0-9a-zA-Z .,\-]*$/', $value, $name);
                }
                $rest = substr($rest, strlen($m[0]));
            }
        }
    }

    public function test_svg_markup_and_legacy_fallback(): void
    {
        $svg = IconLibrary::svg('truck');
        $this->assertStringStartsWith('<svg class="tb-icon-svg tb-icon-svg-truck" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"', $svg);
        $this->assertStringContainsString('stroke="currentColor"', $svg);
        $this->assertStringContainsString('aria-hidden="true"', $svg);
        $this->assertStringContainsString('role="img" aria-label="ارسال"', IconLibrary::svg('truck', IconLibrary::label('truck')));
        $this->assertSame(IconLibrary::svg('truck'), IconLibrary::html(' Truck '));

        $this->assertFalse(IconLibrary::has('🚚'));
        $this->assertFalse(IconLibrary::has('constructor'));
        $this->assertFalse(IconLibrary::has(['truck']));
        $this->assertSame('', IconLibrary::svg('🚚'));
        $this->assertSame('🚚', IconLibrary::html('🚚'));
        $this->assertSame('&lt;img src=x onerror=alert(1)&gt;', IconLibrary::html('<img src=x onerror=alert(1)>'));
        $this->assertSame('', IconLibrary::html(''));
        $this->assertSame('', IconLibrary::html(['x']));
    }

    /** @return iterable<string, array{0:string,1:mixed}> every icon field and default in the registry */
    private static function iconFields(): iterable
    {
        foreach (BlockRegistry::all() as $type => $def) {
            // Sleep Shiman's own theme blocks draw ids such as `bed` or
            // `blanket` from that theme's glyph set (ssIcon()), not this catalog.
            if (str_starts_with($type, 'sleepshiman_')) {
                continue;
            }
            foreach ($def['schema'] ?? [] as $key => $field) {
                yield from self::walkField($type, $key, $field);
            }
        }
    }

    private static function walkField(string $path, string $key, array $field): iterable
    {
        if ($key === 'icon' || ($path === 'icon' && $key === 'name')) {
            yield "{$path}.{$key}" => [$field['type'] ?? '', $field['default'] ?? ''];
        }
        foreach ($field['fields'] ?? [] as $childKey => $child) {
            if (is_array($child)) {
                yield from self::walkField("{$path}.{$key}[]", $childKey, $child);
            }
        }
    }

    public function test_every_icon_field_is_an_icon_control_with_a_catalog_default(): void
    {
        $seen = 0;
        foreach (self::iconFields() as $path => [$type, $default]) {
            $seen++;
            $this->assertSame('icon', $type, "{$path} must use the icon picker");
            $this->assertContains(InspectorFieldRegistry::normalizeField('icon', ['type' => $type])['control'], InspectorFieldRegistry::TYPES);
            $this->assertTrue($default === '' || IconLibrary::has($default), "{$path} default '{$default}' is not a catalog icon");
        }
        $this->assertGreaterThanOrEqual(11, $seen);
    }

    public function test_no_registry_default_still_ships_an_emoji_icon(): void
    {
        // Both stored shapes: PHP arrays ("icon":"…") and JSON-string defaults (\"icon\":\"…\").
        $json = json_encode(BlockRegistry::all(), JSON_UNESCAPED_UNICODE);
        $this->assertDoesNotMatchRegularExpression('/\\\\?"icon\\\\?":\\\\?"[^"\\\\]*[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2190}-\x{21FF}]/u', $json);
    }

    public function test_renderers_draw_icon_names_as_svg_and_legacy_emoji_as_before(): void
    {
        $features = fn (string $icon) => $this->render('features_grid', ['features' => [['icon' => $icon, 'title' => 'T', 'text' => 'x']]]);
        $this->assertStringContainsString('<div class="tb-feature-icon"><svg class="tb-icon-svg tb-icon-svg-truck"', $features('truck'));
        $this->assertStringContainsString('<div class="tb-feature-icon">🚚</div>', $features('🚚'));
        $this->assertStringContainsString('<div class="tb-feature-icon">&lt;b&gt;</div>', $features('<b>'));

        $cases = [
            ['services_cards', ['services' => [['icon' => 'palette', 'title' => 'T', 'text' => 'x']]], 'tb-service-icon'],
            ['service_cards', ['cards' => [['icon' => 'palette', 'title' => 'T', 'description' => 'x']]], 'tb-service-icon'],
            ['contact_info', ['items' => [['icon' => 'phone', 'label' => 'تلفن', 'value' => '۰۲۱']]], 'tb-contact-info-icon'],
            ['trust_badges', ['badges' => [['icon' => 'truck', 'title' => 'T', 'text' => 'x']]], 'tb-trust-icon'],
            ['bento_grid', ['items' => [['icon' => 'zap', 'title' => 'T', 'text' => 'x']]], 'tb-bento-item-icon'],
            ['icon_box', ['icon' => 'star', 'title' => 'T'], 'tb-icon-box-icon'],
            ['trust_item', ['icon' => 'badge-check', 'label' => 'L'], 'tb-trust-item-icon'],
        ];
        foreach ($cases as [$type, $props, $slot]) {
            $html = $this->render($type, $props);
            $this->assertMatchesRegularExpression('#class="' . $slot . '"[^>]*><svg class="tb-icon-svg#', $html, $type);
        }

        $icon = $this->render('icon', ['name' => 'heart', 'size' => 'large']);
        $this->assertStringContainsString('role="img" aria-label="قلب"', $icon);
        $this->assertStringContainsString('>★</span>', $this->render('icon', ['name' => '★']));
    }

    public function test_registry_defaults_render_svg_icons(): void
    {
        foreach (['features_grid', 'trust_badges', 'services_cards', 'bento_grid', 'icon_box', 'trust_item', 'timeline'] as $type) {
            $html = $this->render($type, BlockRegistry::all()[$type]['defaultProps']);
            $this->assertStringContainsString('<svg class="tb-icon-svg', $html, $type);
            $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', strip_tags($html), "{$type} still prints an emoji");
        }
    }

    public function test_the_shared_picker_is_used_by_the_platform_editor_and_styles_only_the_svg(): void
    {
        $canvas = file_get_contents(resource_path('views/admin/theme-builder/canvas.blade.php'));
        $repeater = file_get_contents(resource_path('views/admin/theme-builder/partials/canvas-repeater.blade.php'));
        $this->assertStringContainsString("@include('theme-runtime::canvas.icons')", $canvas);
        $this->assertStringContainsString("schemaDef.type === 'icon'", $canvas);
        $this->assertStringContainsString("@include('theme-runtime::canvas.icon-picker'", $canvas);
        $this->assertStringContainsString("@include('theme-runtime::canvas.icon-picker'", $repeater);
        $this->assertStringNotContainsString("=== 'icon'\">\n                            <input", $repeater);

        $css = file_get_contents(base_path(self::RUNTIME . '/resources/css/theme-runtime-blocks.css'));
        $this->assertStringContainsString('.tb-icon-svg { display: inline-block; width: 1em; height: 1em;', $css);
        // Slot colours apply to the SVG only, so a legacy emoji or ✓ keeps its colour.
        $this->assertDoesNotMatchRegularExpression('/\.tb-(feature|service|trust|contact-info)-icon\s*\{[^}]*color/', substr($css, strpos($css, 'Phase 0.6 (ADR-065)')));
    }

    public function test_the_picker_view_renders_with_the_catalog(): void
    {
        $html = view('theme-runtime::canvas.icons')->render();
        $this->assertStringContainsString('window.CanvasIcons.load(', $html);
        $this->assertStringNotContainsString('<path', substr($html, strpos($html, 'window.CanvasIcons.load(')), 'SVG tags are <-escaped inside the script');
        $this->assertStringContainsString('"truck"', $html);
    }
}
