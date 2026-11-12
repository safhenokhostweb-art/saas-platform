<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\ThemeBuilder\BlockRenderer;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use Tests\TestCase;

/**
 * Phase 0.2b (docs/theme-builder/COMMERCIAL_WIDGET_ROADMAP.md, ADR-061) —
 * the registry contract behind the Canvas's nested list editor, the `link`
 * type on every navigational URL field, and the strict pricing highlight.
 * customer-site pins the same contract from its side
 * (CanonicalListSchemaParityTest).
 */
class Phase02bNestedListContractTest extends TestCase
{
    /** The Canvas repeater edits depths 0, 1 and 2 (InspectorFieldRegistry maxDepth cap). */
    private const MAX_DEPTH = 2;

    /** URL-named fields that are not navigation: media sources and data endpoints. */
    private const NON_NAVIGATIONAL_URLS = [
        'video.url', 'header_modern_v1.logoUrl',
        'dynamic_product_grid.endpoint_url', 'dynamic_feed_slider.endpoint_url',
        // ADR-083: the same data_source identifier on the spec's list widgets.
        'event_calendar.endpoint_url', 'dual_sale_product_grid.endpoint_url',
        'course_video_filter.endpoint_url', 'downloadable_assets.endpoint_url',
    ];

    private const LINK_FIELDS = [
        'slider.slides.link', 'promo_banner.link', 'client_logos.logos.url', 'timeline.items.link_url',
        'pricing_cards.plans.cta_url', 'services_cards.services.link', 'nav_header.logo_url',
        'nav_header.nav_links.url', 'nav_header.nav_links.mega_columns.items.url',
        'nav_header.nav_links.mega_banner_url', 'nav_header.cta_url', 'nav_footer.columns.links.url',
        'nav_footer.links.url', 'nav_footer.social_links.url', 'about_section.btn_url',
        'service_cards.cards.url', 'project_cards.cards.url', 'header_modern_v1.homeUrl',
        'header_modern_v1.ctaUrl', 'foodmart_hero.slides.cta_url', 'foodmart_hero.promos.cta_url',
    ];

    private function render(string $type, array $props): string
    {
        BlockRenderer::resetCarouselScriptEmission();
        return (new BlockRenderer())->renderBlock(['type' => $type, 'id' => 'b1', 'props' => $props]);
    }

    /** @return iterable<string, array{0: array, 1: int}> every field, by dotted path, with its list depth */
    private function fields(): iterable
    {
        $walk = function (string $path, array $schema, int $depth) use (&$walk): iterable {
            foreach ($schema as $key => $field) {
                if (!is_array($field)) {
                    continue;
                }
                yield "{$path}.{$key}" => [$field, $depth];
                if (($field['type'] ?? null) === 'repeater') {
                    yield from $walk("{$path}.{$key}", $field['fields'] ?? [], $depth + 1);
                }
            }
        };
        foreach (BlockRegistry::all() as $type => $definition) {
            yield from $walk($type, $definition['schema'] ?? [], 0);
        }
    }

    public function test_nested_lists_stay_within_the_editor_depth_and_declare_their_items(): void
    {
        $nested = 0;
        foreach ($this->fields() as $path => [$field, $depth]) {
            if (($field['type'] ?? null) !== 'repeater') {
                continue;
            }
            $this->assertLessThanOrEqual(self::MAX_DEPTH, $depth, "{$path}: the Canvas edits lists up to three levels deep");
            if ($depth > 0) {
                $nested++;
                $this->assertNotEmpty($field['fields'] ?? [], "{$path}: a nested list needs child fields");
                $this->assertNotSame('', $field['item_label'] ?? '', "{$path}: a nested list needs an item_label for its add button");
            }
        }
        $this->assertGreaterThanOrEqual(4, $nested, 'pricing features, footer links and both mega-menu levels');
    }

    public function test_no_navigational_url_field_is_plain_text(): void
    {
        $plain = [];
        foreach ($this->fields() as $path => [$field]) {
            $key = substr($path, strrpos($path, '.') + 1);
            if (preg_match('/(^|_)(url|href|link)$|Url$/', $key) !== 1 || in_array($path, self::NON_NAVIGATIONAL_URLS, true)) {
                continue;
            }
            if (in_array($field['type'] ?? null, ['string', 'text'], true)) {
                $plain[] = $path;
            }
        }
        $this->assertSame([], $plain, "URL fields must be `link` fields (escapeUrl-rendered, URL input):\n" . implode("\n", $plain));
    }

    public function test_every_converted_link_field_renders_structured_links_and_refuses_unsafe_urls(): void
    {
        foreach (self::LINK_FIELDS as $i => $path) {
            $type = strtok($path, '.');
            $props = $this->sampleProps($type);

            $structured = $this->render($type, $this->withValueAt($props, $path, ['url' => "/structured-ok-{$i}", 'target' => '_self']));
            $this->assertStringContainsString("href=\"/structured-ok-{$i}\"", $structured, "{$path}: a structured {url} link must render its url");

            $unsafe = $this->render($type, $this->withValueAt($props, $path, 'javascript:alert(1)'));
            $this->assertStringNotContainsStringIgnoringCase('javascript:', $unsafe, "{$path}: an unsafe scheme must never reach an href");
        }
    }

    public function test_pricing_highlight_reads_legacy_text_values_strictly(): void
    {
        $plans = [];
        foreach ([true, 'true', '1', 1, false, 'false', '0', '', 'no'] as $value) {
            $plans[] = ['name' => 'پلن', 'price' => '۱', 'highlight' => $value, 'features' => []];
        }
        $html = $this->render('pricing_cards', ['plans' => $plans]);
        $this->assertSame(4, substr_count($html, 'tb-pricing-card highlighted'), 'only true, "true", "1" and 1 highlight a plan');
    }

    private function sampleProps(string $type): array
    {
        $definition = BlockRegistry::get($type);
        $props = $definition['defaultProps'] ?? [];
        foreach ($definition['schema'] as $key => $field) {
            $props[$key] = $this->sampleValue($key, $field);
        }
        return $props;
    }

    private function sampleValue(string $key, array $field): mixed
    {
        return match ($field['type'] ?? 'string') {
            'repeater' => [array_combine(array_keys($field['fields'] ?? []), array_map(
                fn ($k, $f) => $this->sampleValue($k, $f), array_keys($field['fields'] ?? []), $field['fields'] ?? []
            ))],
            'boolean' => true,
            'number', 'range' => 1,
            'select' => (string) array_key_first($field['options']),
            'image' => "/storage/media/{$key}.jpg",
            'link' => "/sample/{$key}",
            'array' => [],
            default => "نمونه {$key}",
        };
    }

    private function withValueAt(array $props, string $path, mixed $value): array
    {
        $keys = array_slice(explode('.', $path), 1);
        $ref = &$props;
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $ref[$key] = $value;
                break;
            }
            $ref = &$ref[$key][0];
        }
        unset($ref);
        return $props;
    }
}
