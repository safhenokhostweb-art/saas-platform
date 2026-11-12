<?php

namespace Tests\Unit\ThemeBuilder;

use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\BlockRenderer;
use SaaSPlatform\ThemeRuntime\CommercialBlockTypes;
use SaaSPlatform\ThemeRuntime\DataBinding\DataFetcher;
use SaaSPlatform\ThemeRuntime\DataBinding\DataFetchResult;
use SaaSPlatform\ThemeRuntime\TemplateLibrary;
use SaaSPlatform\ThemeRuntime\Widget\WidgetSchemaContract;
use Tests\TestCase;

/**
 * PLATFORM-THEME-SPEC-v1 (ADR-083): the seven commercial widgets and the
 * spec's blueprints.
 *
 * The sweep is the "no 500 on bad props" gate: every schema field of every
 * widget is replaced, one at a time and then all at once, by values the
 * editor, an import or a hand-edited theme could produce, in both render
 * modes. Rendering must return markup, never throw, and never echo a
 * hostile value unescaped.
 */
class CommercialWidgetResilienceTest extends TestCase
{
    private const HOSTILE = '<script>alert(1)</script>';

    private function renderer(string $mode = BlockRenderer::MODE_EDITOR, array $context = []): BlockRenderer
    {
        return (new BlockRenderer())->setContext($context)->setMode($mode);
    }

    private function definition(string $type): array
    {
        return BlockRegistry::all()[$type];
    }

    /** @return list<mixed> */
    private function garbage(): array
    {
        return [
            null, '', '   ', [], ['nested' => ['deep' => [1, 2, 3]]], [['x'], 'scalar', 7], 0, -1, 99999999, 1.5e20, true, false,
            self::HOSTILE, '"><img src=x onerror=alert(1)>', 'javascript:alert(1)', '{"broken', '[1,2', str_repeat('ا', 5000),
        ];
    }

    public function test_the_seven_widgets_are_registered_and_meet_the_schema_contract(): void
    {
        $all = BlockRegistry::all();
        foreach (CommercialBlockTypes::TYPES as $type) {
            $this->assertArrayHasKey($type, $all, $type);
            $this->assertTrue(BlockRegistry::isHostRegisteredType($type), "{$type} goes through registerHostType");
            $this->assertNotContains($all[$type]['category'], WidgetSchemaContract::THEME_PACK_CATEGORIES, "{$type} is neutral, so it shows in the palette");
            $this->assertSame('none', $all[$type]['defaultProps']['section_spacing'], "{$type} keeps the zero-gap default");
        }
        $this->assertSame([], WidgetSchemaContract::violations(array_intersect_key($all, array_flip(CommercialBlockTypes::TYPES))));

        foreach (['event_calendar', 'dual_sale_product_grid', 'course_video_filter', 'downloadable_assets'] as $type) {
            $this->assertSame('data_source', $all[$type]['data_source']['property'] ?? null, "{$type} declares the data_source contract");
            $this->assertSame('items', $all[$type]['data_source']['manual_prop'] ?? null);
        }
    }

    public function test_every_widget_renders_its_defaults_without_a_placeholder(): void
    {
        foreach (CommercialBlockTypes::TYPES as $type) {
            $html = $this->renderer()->renderBlock(['id' => "d-{$type}", 'type' => $type, 'props' => $this->definition($type)['defaultProps']]);
            $this->assertStringContainsString('class="tb-cx tb-cx--', $html, $type);
            $this->assertStringNotContainsString('tb-placeholder', $html, $type);
            $this->assertStringNotContainsString('tb-cx-space--regular', $html, "{$type} adds no vertical spacing by default");
        }
    }

    public function test_malformed_props_never_throw_and_never_leak_markup(): void
    {
        $renders = 0;
        foreach ([BlockRenderer::MODE_EDITOR, BlockRenderer::MODE_PUBLIC] as $mode) {
            foreach (CommercialBlockTypes::TYPES as $type) {
                $definition = $this->definition($type);
                $variants = [[], ['items' => 'not json'], ['rows' => [self::HOSTILE]], ['stats' => [['value' => ['x'], 'label' => new \ArrayObject()]]]];
                foreach (array_keys($definition['schema']) as $key) {
                    foreach ($this->garbage() as $value) {
                        $variants[] = [$key => $value] + $definition['defaultProps'];
                    }
                }
                $allBad = [];
                foreach (array_keys($definition['schema']) as $key) {
                    $allBad[$key] = self::HOSTILE;
                }
                $variants[] = $allBad;

                foreach ($variants as $i => $props) {
                    $html = $this->renderer($mode)->renderBlock(['id' => "m{$i}", 'type' => $type, 'props' => $props]);
                    $renders++;
                    $this->assertIsString($html);
                    $this->assertStringNotContainsString(self::HOSTILE, $html, "{$type} #{$i} ({$mode}) echoed a script");
                    $this->assertStringNotContainsString('<img src=x', $html, "{$type} #{$i} ({$mode}) echoed a tag");
                    $this->assertDoesNotMatchRegularExpression('/(href|src)="\s*javascript:/i', $html, "{$type} #{$i} ({$mode}) emitted a javascript: URL");
                }
            }
        }
        $this->assertGreaterThan(1000, $renders);
    }

    public function test_scalar_props_and_hostile_repeater_items_render_safely(): void
    {
        foreach (CommercialBlockTypes::TYPES as $type) {
            $this->assertIsString($this->renderer()->renderBlock(['id' => 's', 'type' => $type, 'props' => 'legacy scalar']));
        }

        $html = $this->renderer()->renderBlock(['id' => 'x', 'type' => 'downloadable_assets', 'props' => ['items' => [
            ['title' => 'a', 'file_url' => 'javascript:alert(1)'],
            ['title' => 'b', 'file_url' => ['type' => 'entity', 'url' => 'https://example.com/file.pdf']],
        ]]]);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('href="https://example.com/file.pdf" download', $html);
    }

    public function test_an_internal_source_falls_back_to_authored_items_and_honours_an_empty_answer(): void
    {
        $props = ['source_type' => 'internal', 'endpoint_url' => 'products', 'query_params' => '{}', 'items' => [['title' => 'دستی']]];
        $render = fn (array $context) => $this->renderer(BlockRenderer::MODE_PUBLIC, $context)->renderBlock(['id' => 'p', 'type' => 'dual_sale_product_grid', 'props' => $props]);

        $this->assertStringContainsString('دستی', $render([]), 'no resolver: the authored items show');
        $this->assertStringContainsString('دستی', $render(['data_fetcher' => new DataFetcher(null, fn () => throw new \RuntimeException('down'))]), 'a failing resolver never blanks the page');

        $live = $render(['data_fetcher' => new DataFetcher(null, fn () => [['title' => 'زنده', 'url' => '/shop/p']])]);
        $this->assertStringContainsString('زنده', $live);
        // The wrapper's data-tb-block-props still carries the manual items; the list does not.
        $this->assertStringNotContainsString('دستی', strstr($live, '<section'));

        $empty = $render(['data_fetcher' => new DataFetcher(null, fn () => DataFetchResult::empty())]);
        $this->assertStringContainsString('tb-cx-empty', $empty);
    }

    public function test_the_product_grid_uses_the_shared_cart_when_the_host_has_one(): void
    {
        $props = ['items' => [['product_id' => '42', 'title' => 'پنل', 'url' => '/shop/panel']]];
        $withoutCart = $this->renderer()->renderBlock(['id' => 'c', 'type' => 'dual_sale_product_grid', 'props' => $props]);
        $this->assertStringNotContainsString('data-tb-add-to-cart', $withoutCart, 'no cart endpoints: a plain link');

        $withCart = $this->renderer(BlockRenderer::MODE_PUBLIC, ['site_endpoints' => ['cart' => ['add' => '/cart/add', 'mini' => '/cart/mini', 'remove' => '/cart/remove', 'page' => '/cart']]])
            ->renderBlock(['id' => 'c', 'type' => 'dual_sale_product_grid', 'props' => $props]);
        $this->assertStringContainsString('data-tb-add-to-cart', $withCart, 'the drawer, badges and toast of cart.js take over');
        $this->assertStringContainsString('data-product-id="42"', $withCart);
    }

    public function test_the_calendar_places_events_on_their_weekday(): void
    {
        $html = $this->renderer()->renderBlock(['id' => 'cal', 'type' => 'event_calendar', 'props' => [
            'days_in_month' => 31, 'first_weekday' => 'fri', 'items' => [['title' => 'دوره', 'day' => '۲', 'status' => 'running', 'url' => '/courses']],
        ]]);

        $this->assertSame(6, substr_count($html, 'tb-cx-cal-day--pad'), 'a month starting on Friday is preceded by six blanks');
        $this->assertSame(31, preg_match_all('/class="tb-cx-cal-day(?: tb-cx-cal-day--event[^"]*)?"/', $html));
        $this->assertStringContainsString('tb-cx-cal-day--event tb-cx-status--running', $html);
    }

    public function test_the_course_filter_ships_a_scoped_script_per_grid(): void
    {
        $props = $this->definition('course_video_filter')['defaultProps'];
        $one = $this->renderer()->renderBlock(['id' => 'one', 'type' => 'course_video_filter', 'props' => $props]);
        $two = $this->renderer()->renderBlock(['id' => 'two', 'type' => 'course_video_filter', 'props' => $props]);

        preg_match('/id="(tb-cx-courses-[0-9a-f]{12})"/', $one, $a);
        preg_match('/id="(tb-cx-courses-[0-9a-f]{12})"/', $two, $b);
        $this->assertNotSame($a[1], $b[1]);
        $this->assertStringContainsString('getElementById("' . $a[1] . '")', $one);
        $this->assertStringContainsString('aria-pressed="true"', $one);
        $this->assertSame(2, substr_count($one, 'data-access="paid"'));
    }

    public function test_the_spec_blueprints_exist_and_carry_no_colour(): void
    {
        $docs = array_filter(TemplateLibrary::documents(), fn (array $doc) => ($doc['spec'] ?? null) === 'PLATFORM-THEME-SPEC-v1');
        $this->assertSame(['header', 'footer'], array_values(array_intersect(['header', 'footer'], array_column($docs, 'type'))));
        $routes = array_column(array_filter($docs, fn ($d) => $d['type'] === 'page'), 'route');
        sort($routes);
        $this->assertSame(['about', 'contact', 'courses', 'home', 'shop'], $routes, 'the five pages: /, /about, /courses, /products (= shop), /contact');

        foreach ($docs as $key => $doc) {
            $json = json_encode($doc['blocks'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(/i', $json, "{$key} hardcodes a colour; use --tb-* tokens");
            // Owner decision 2026-11-12: library files never point at one tenant's domain.
            $this->assertStringNotContainsString('https://oxinsolar.org', $json, "{$key} links the reference site");
            preg_match_all('/"[a-z_]*color[a-z_]*":"([^"]*)"/', $json, $colours);
            foreach ($colours[1] as $colour) {
                $this->assertStringContainsString('var(--tb-', $colour, "{$key}: {$colour}");
            }
        }

        $header = TemplateLibrary::find('lib:solar-header')['blocks'][0]['props'];
        $this->assertTrue($header['sticky']);
        $this->assertSame(60, $header['scroll_threshold'], 'the header turns solid past 60px');
        $this->assertGreaterThan(0, $header['backdrop_blur']);
        $this->assertTrue($header['show_cart']);
        foreach ($header['nav_links'] as $link) {
            if (isset($link['mega_columns'])) {
                $this->assertCount(3, $link['mega_columns'], $link['label'] . ' is a three-column mega dropdown');
            }
        }
    }

    public function test_the_commercial_stylesheet_paints_only_with_tokens(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 3) . '/packages/saas-platform/modules/theme-runtime/resources/css/theme-runtime-blocks.css');
        $start = strpos($css, 'PLATFORM-THEME-SPEC-v1 commercial widgets');
        $this->assertNotFalse($start);
        $block = substr($css, $start);

        // Drop every `var(--x, fallback)`, innermost first; a colour left over is hardcoded.
        do {
            $block = preg_replace('/var\(--[a-z0-9-]+(?:,\s*[^()]*)?\)/i', 'TOKEN', $block, -1, $count);
        } while ($count > 0);
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(/i', $block);
    }
}
