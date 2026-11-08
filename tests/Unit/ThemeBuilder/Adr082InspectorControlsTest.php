<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\Inspector\InspectorFieldRegistry;
use SaaSPlatform\ThemeRuntime\Layout\LayoutContract;
use SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider;
use SaaSPlatform\ThemeRuntime\Widget\WidgetSchemaContract;
use Tests\TestCase;

/**
 * ADR-082: the Elementor-grade Inspector vocabulary — explicit tabs
 * (`panel`), `condition`, per-device values (`responsive`), Normal / Hover /
 * Active (`state_group`), the range / spacing / typography / shadow
 * controls, `editor.hideGenericStyle` — and the 768px mobile breakpoint.
 */
class Adr082InspectorControlsTest extends TestCase
{
    private const BASE = ['name' => 'W', 'category' => 'content', 'icon' => 'box', 'defaultProps' => []];

    private static function field(array $extra = []): array
    {
        return ['type' => 'string', 'label' => 'A'] + $extra;
    }

    // ── Contract ──────────────────────────────────────────────────────

    public function test_the_whole_registry_meets_the_extended_contract(): void
    {
        $this->assertSame([], WidgetSchemaContract::violations(BlockRegistry::all()));
    }

    public function test_the_contract_names_each_new_kind_of_drift(): void
    {
        $state = fn (string $s, array $x = []) => ['type' => 'color', 'label' => $s, 'state_group' => 'menu', 'state' => $s] + $x;
        $cases = [
            'w.a: panel must be one of content/style/advanced' => ['a' => self::field(['panel' => 'responsive'])],
            'w.a: condition must map field names to values' => ['a' => self::field(['condition' => ['b']]), 'b' => self::field()],
            "w.a: condition references unknown field 'zz'" => ['a' => self::field(['condition' => ['zz' => true]])],
            'w.a: condition cannot depend on the field itself' => ['a' => self::field(['condition' => ['a!' => '']])],
            "w.a: condition value for 'b' must be a scalar or a list of scalars" => ['a' => self::field(['condition' => ['b' => [['x']]]]), 'b' => self::field()],
            'w.a: responsive is supported on number/range/select/spacing/typography only' => ['a' => ['type' => 'color', 'label' => 'A', 'responsive' => true]],
            "w.a: 'a_mobile' is this responsive field's mobile value and cannot be a field of its own" => ['a' => ['type' => 'range', 'label' => 'A', 'responsive' => true], 'a_mobile' => ['type' => 'range', 'label' => 'M']],
            'w.a: mobile_default applies to responsive fields only' => ['a' => ['type' => 'range', 'label' => 'A', 'mobile_default' => 3]],
            'w.a: units must be a list of px/rem/em/%/vw/vh' => ['a' => ['type' => 'range', 'label' => 'A', 'units' => ['pt']]],
            'w.a: unit is not one of its units' => ['a' => ['type' => 'range', 'label' => 'A', 'units' => ['px', '%'], 'unit' => 'rem']],
            'w.a: min must be below max' => ['a' => ['type' => 'range', 'label' => 'A', 'min' => 10, 'max' => 10]],
            'w.a: state needs a state_group' => ['a' => self::field(['state' => 'hover'])],
            "w: state_group 'menu' needs at least two states" => ['a' => $state('normal')],
            "w: state_group 'menu' has no normal state" => ['a' => $state('hover'), 'b' => $state('active')],
            "w: state_group 'menu' repeats a state" => ['a' => $state('normal'), 'b' => $state('normal')],
            "w: state_group 'menu' fields must be adjacent" => ['a' => $state('normal'), 'x' => self::field(), 'b' => $state('hover')],
            "w: state_group 'menu' spans several tabs or sections" => ['a' => $state('normal'), 'b' => $state('hover', ['panel' => 'style'])],
            'w.b: state must be one of normal/hover/active' => ['a' => $state('normal'), 'b' => $state('focus')],
        ];
        foreach ($cases as $expected => $schema) {
            $violations = WidgetSchemaContract::definitionViolations('w', self::BASE + ['schema' => $schema]);
            $this->assertContains($expected, $violations, $expected . ' — got: ' . implode(' | ', $violations));
        }

        $hidden = WidgetSchemaContract::definitionViolations('w', self::BASE + ['schema' => [], 'editor' => ['hideGenericStyle' => ['colors', 'glow']]]);
        $this->assertContains('w: editor.hideGenericStyle must be true or a list of typography/colors/spacing/border/shadow/opacity', $hidden);
    }

    public function test_a_complete_header_style_schema_is_valid(): void
    {
        $schema = [
            'show_search' => ['type' => 'boolean', 'label' => 'S', 'default' => false],
            'search_mode' => ['type' => 'select', 'label' => 'M', 'options' => ['input' => 'I', 'icon' => 'C'], 'default' => 'input', 'condition' => ['show_search' => true]],
            'logo_height' => ['type' => 'range', 'label' => 'H', 'unit' => 'px', 'min' => 16, 'max' => 120, 'default' => 40, 'mobile_default' => 32, 'responsive' => true],
            'logo_max_width' => ['type' => 'range', 'label' => 'W', 'units' => ['px', '%'], 'unit' => 'px', 'default' => 224, 'unit_limits' => ['%' => ['min' => 0, 'max' => 100]]],
            'padding' => ['type' => 'spacing', 'label' => 'P', 'panel' => 'style', 'responsive' => true],
            'menu_typography' => ['type' => 'typography', 'label' => 'T', 'panel' => 'style', 'group' => 'منو', 'responsive' => true],
            'menu_color' => ['type' => 'color', 'label' => 'C', 'panel' => 'style', 'group' => 'منو', 'state_group' => 'menu_colors', 'state' => 'normal'],
            'menu_color_hover' => ['type' => 'color', 'label' => 'C', 'panel' => 'style', 'group' => 'منو', 'state_group' => 'menu_colors', 'state' => 'hover'],
            'menu_color_active' => ['type' => 'color', 'label' => 'C', 'panel' => 'style', 'group' => 'منو', 'state_group' => 'menu_colors', 'state' => 'active'],
            'shadow' => ['type' => 'shadow', 'label' => 'S', 'panel' => 'style'],
            'sticky' => ['type' => 'boolean', 'label' => 'St', 'panel' => 'advanced', 'default' => false],
        ];
        $definition = self::BASE + ['schema' => $schema, 'editor' => ['hideGenericStyle' => true], 'defaultProps' => ['logo_height_mobile' => 30]];
        $this->assertSame([], WidgetSchemaContract::definitionViolations('w', $definition), 'a responsive device key may be a default prop');

        $normalized = InspectorFieldRegistry::normalizeDefinition($definition);
        $this->assertSame(array_keys(InspectorFieldRegistry::GENERIC_STYLE_SECTIONS), $normalized['editor']['hideGenericStyle']);
        $this->assertSame('style', $normalized['schema']['menu_color']['panel'], 'declared panels are kept');
        $this->assertSame('content', $normalized['schema']['logo_height']['panel']);
        $this->assertSame('shadow', $normalized['schema']['shadow']['control']);
        $this->assertSame('range', $normalized['schema']['logo_height']['control']);
        $this->assertTrue($normalized['schema']['logo_height']['responsive']);
    }

    // ── Normalisation ─────────────────────────────────────────────────

    public function test_a_field_without_a_panel_stays_in_the_content_tab_whatever_its_type(): void
    {
        foreach (['color', 'spacing', 'typography', 'alignment', 'shadow'] as $type) {
            $this->assertSame('content', InspectorFieldRegistry::normalizeField('a', ['type' => $type])['panel'], $type);
        }
        $this->assertSame([], InspectorFieldRegistry::normalizeDefinition(['schema' => []])['editor']['hideGenericStyle']);
        $this->assertSame(['colors'], InspectorFieldRegistry::normalizeHiddenStyleSections(['colors', 'bogus']));
    }

    public function test_responsive_values_inherit_down_the_devices_with_device_defaults(): void
    {
        $field = ['default' => 40, 'mobile_default' => 32];
        $this->assertSame(40, InspectorFieldRegistry::responsiveValue([], 'h', 'desktop', $field));
        $this->assertSame(56, InspectorFieldRegistry::responsiveValue(['h' => 56], 'h', 'tablet', $field));
        $this->assertSame(32, InspectorFieldRegistry::responsiveValue(['h' => 56], 'h', 'mobile', $field), 'a device default wins over inheritance');
        $this->assertSame(44, InspectorFieldRegistry::responsiveValue(['h' => 56, 'h_tablet' => 44], 'h', 'tablet', $field));
        $this->assertSame(28, InspectorFieldRegistry::responsiveValue(['h_mobile' => 28], 'h', 'mobile', $field));
        $this->assertSame(44, InspectorFieldRegistry::responsiveValue(['h_tablet' => 44], 'h', 'mobile'), 'no device default: the tablet value');
        $this->assertSame('h_tablet', InspectorFieldRegistry::deviceKey('h', 'tablet'));
    }

    public function test_shadow_and_multi_unit_range_values_are_normalised_safely(): void
    {
        $shadow = InspectorFieldRegistry::normalizeValue(['type' => 'shadow'], ['x' => 0, 'y' => 500, 'blur' => -4, 'spread' => '2', 'color' => 'rgba(0, 0, 0, 0.2)', 'inset' => true]);
        $this->assertSame(['x' => 0, 'y' => 100, 'blur' => 0, 'spread' => 2, 'color' => 'rgba(0, 0, 0, 0.2)', 'inset' => true], $shadow);
        $this->assertNull(InspectorFieldRegistry::normalizeValue(['type' => 'shadow'], ''));
        try {
            InspectorFieldRegistry::normalizeValue(['type' => 'shadow'], ['color' => 'red;background:url(x)']);
            $this->fail('an unsafe shadow colour must be rejected');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $range = ['type' => 'range', 'units' => ['px', '%'], 'unit' => 'px', 'min' => 0, 'max' => 400, 'unit_limits' => ['%' => ['min' => 0, 'max' => 100]]];
        $this->assertSame(['size' => 100, 'unit' => '%'], InspectorFieldRegistry::normalizeValue($range, ['size' => 180, 'unit' => '%']));
        $this->assertSame(['size' => 224, 'unit' => 'px'], InspectorFieldRegistry::normalizeValue($range, 224), 'a legacy number keeps the default unit');
        $this->assertSame(120, InspectorFieldRegistry::normalizeValue(['type' => 'range', 'min' => 16, 'max' => 120], 500));
        $this->expectException(\InvalidArgumentException::class);
        InspectorFieldRegistry::normalizeValue($range, ['size' => 10, 'unit' => 'vw']);
    }

    public function test_the_js_model_uses_the_php_vocabulary(): void
    {
        $js = (string) file_get_contents(ThemeRuntimeServiceProvider::canvasControlsPath());
        $list = function (string $name) use ($js): array {
            $this->assertSame(1, preg_match('/const ' . $name . ' = (\[[^\]]*\]);/', $js, $m), $name);
            return json_decode(str_replace("'", '"', $m[1]), true);
        };
        $this->assertSame(InspectorFieldRegistry::EDITOR_PANELS, $list('PANELS'));
        $this->assertSame(InspectorFieldRegistry::RESPONSIVE_CONTROLS, $list('RESPONSIVE_CONTROLS'));
        $this->assertSame(InspectorFieldRegistry::STATES, $list('STATES'));
        $this->assertSame(InspectorFieldRegistry::UNITS, $list('UNITS'));
        $this->assertSame(array_keys(InspectorFieldRegistry::DEVICE_SUFFIXES), $list('DEVICES'));
        foreach (InspectorFieldRegistry::DEVICE_SUFFIXES as $device => $suffix) {
            $this->assertStringContainsString("{$device}: '{$suffix}'", $js);
        }
        foreach (InspectorFieldRegistry::GENERIC_STYLE_SECTIONS as $section => $keys) {
            $this->assertStringContainsString("{$section}: ['" . implode("', '", $keys) . "']", $js, $section);
        }
        $this->assertStringContainsString("x: [-100, 100], y: [-100, 100], blur: [0, 200], spread: [-100, 100]", $js, 'shadow limits');
    }

    // ── Breakpoints ───────────────────────────────────────────────────

    public function test_the_mobile_override_is_the_768px_platform_breakpoint(): void
    {
        $this->assertSame(['tablet' => 1024, 'mobile' => 768], LayoutContract::BREAKPOINT_MAX_WIDTH_BY_NAME);
        $html = (new BlockRenderer())->renderBlock([
            'type' => 'heading', 'id' => 'h1', 'props' => ['text' => 'عنوان'],
            'responsive' => ['mobile' => ['paddingTop' => 8]],
        ]);
        $this->assertStringContainsString('@media(max-width:767.98px){.tb-blk-h1{padding-top:8px}}', $html);
    }

    // ── nav_header ────────────────────────────────────────────────────

    public function test_the_header_splits_its_fields_into_content_style_and_advanced(): void
    {
        $definition = BlockRegistry::all()['nav_header'];
        $schema = InspectorFieldRegistry::normalizeDefinition($definition)['schema'];
        $byPanel = [];
        foreach ($schema as $key => $field) {
            $byPanel[$field['panel']][] = $key;
        }
        $this->assertSame([
            'bg_color', 'text_color',
            // step 3: the header container, then the menu
            'min_height', 'padding', 'items_gap', 'content_width', 'content_max_width', 'border_bottom_width', 'border_bottom_color', 'header_shadow', 'backdrop_blur',
            'menu_typography', 'menu_gap', 'menu_item_padding_x', 'menu_item_padding_y', 'menu_color', 'menu_color_hover', 'menu_color_active', 'menu_bg', 'menu_bg_hover', 'menu_bg_active',
            'pointer', 'pointer_animation', 'pointer_color', 'pointer_thickness',
            // step 4: the phone drawer and burger
            'drawer_bg', 'drawer_text', 'drawer_divider_color', 'drawer_width', 'drawer_overlay_color', 'burger_color', 'burger_size',
            'cta_glow_enabled', 'cta_glow_color',
        ], $byPanel['style']);
        $this->assertSame(['menu_color', 'menu_color_hover', 'menu_color_active'], array_keys(array_filter($schema, fn ($f) => ($f['state_group'] ?? null) === 'menu_text')));
        $this->assertSame([
            'sticky', 'sticky_hide_on_scroll',
            // step 4: sticky options and the scrolled look
            'sticky_devices', 'sticky_offset', 'scroll_threshold', 'sticky_shrink', 'sticky_shrink_height', 'sticky_logo_scale', 'scrolled_bg_color', 'scrolled_text_color', 'scrolled_shadow',
            'transparent_over_hero', 'transparent_text_color',
            'burger_breakpoint', 'drawer_style', 'drawer_side',
        ], $byPanel['advanced']);
        $this->assertContains('logo_image', $byPanel['content']);
        $this->assertSame(true, $definition['editor']['hideGenericStyle']);

        $this->assertSame(['nav_source' => 'custom'], $schema['nav_links']['condition']);
        $this->assertSame(['show_search' => true], $schema['search_mode']['condition']);
        $this->assertSame(['sticky' => true], $schema['sticky_hide_on_scroll']['condition']);
        $this->assertSame(['transparent_over_hero' => true], $schema['transparent_text_color']['condition']);
        $this->assertArrayNotHasKey('condition', $schema['cta_glow_color'], 'a colour alone turns the glow on, so it stays editable');
        $this->assertSame(['range', 'px'], [$schema['logo_height']['type'], $schema['logo_height']['unit']]);
    }

    public function test_the_header_still_renders_from_the_same_props(): void
    {
        $html = (new BlockRenderer())->renderBlock(['type' => 'nav_header', 'id' => 'h', 'props' => [
            'logo_image' => '/l.png', 'logo_height' => 48, 'logo_height_mobile' => 30, 'sticky' => true, 'bg_color' => '#0f172a',
        ]]);
        $this->assertStringContainsString('--tb-logo-h:48px;--tb-logo-h-m:30px', $html);
        $this->assertStringContainsString('data-tb-header="sticky"', $html);
        $this->assertStringContainsString('background:#0f172a', $html);
    }

    // ── Editors ───────────────────────────────────────────────────────

    public function test_the_platform_editor_renders_every_tab_from_the_schema(): void
    {
        $canvas = (string) file_get_contents(resource_path('views/admin/theme-builder/canvas.blade.php'));
        $this->assertStringContainsString("@include('theme-runtime::canvas.controls')", $canvas);
        $this->assertStringContainsString('...window.CanvasControls.editorMixin(),', $canvas);
        $this->assertStringContainsString('ctlSetProp(key, value) {', $canvas);
        $this->assertStringContainsString('x-for="(schemaDef, propKey) in panelFields(selectedTab)"', $canvas);
        $this->assertStringNotContainsString('in selectedSchema"', $canvas, 'the field loop is filtered per tab');
        foreach (['control-states', 'control-devices', 'control-field'] as $partial) {
            $this->assertStringContainsString("@include('theme-runtime::canvas.{$partial}')", $canvas);
        }
        foreach (array_keys(InspectorFieldRegistry::GENERIC_STYLE_SECTIONS) as $section) {
            $this->assertStringContainsString("x-show=\"genericStyleVisible('{$section}')\"", $canvas, $section);
        }
        $this->assertStringContainsString("schemaDef.type === 'number' && !ctlIsResponsive(propKey)", $canvas);
    }

    public function test_the_shared_controls_render_and_carry_their_model(): void
    {
        $view = $this->app['view'];
        $controls = $view->make('theme-runtime::canvas.controls')->render();
        $this->assertStringContainsString('root.CanvasControls = api;', $controls);
        $this->assertStringContainsString('.tbc-sides', $controls);

        $field = $view->make('theme-runtime::canvas.control-field')->render();
        foreach (["schemaDef.type === 'range'", "schemaDef.type === 'spacing'", "schemaDef.type === 'typography'", "schemaDef.type === 'shadow'"] as $needle) {
            $this->assertStringContainsString($needle, $field);
        }
        // Scope variables are `tbc`-prefixed: a loop variable named `device`
        // swallowed the editor's own setDevice() write (Alpine scope shadowing).
        $devices = $view->make('theme-runtime::canvas.control-devices')->render();
        $this->assertStringContainsString('ctlSetDevice(tbcDevice)', $devices);
        $this->assertStringNotContainsString('x-for="device in', $devices);
        $this->assertStringContainsString('ctlSetState(propKey, tbcState.state)', $view->make('theme-runtime::canvas.control-states')->render());
        $this->assertDoesNotMatchRegularExpression('/x-for="(?!tbc)[a-zA-Z]+ in/', (string) file_get_contents(base_path('packages/saas-platform/modules/theme-runtime/resources/views/canvas/control-field.blade.php')), 'every loop variable is prefixed');
    }
}
