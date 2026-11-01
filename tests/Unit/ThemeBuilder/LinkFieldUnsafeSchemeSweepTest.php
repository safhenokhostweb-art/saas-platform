<?php

namespace Tests\Unit\ThemeBuilder;

use DOMDocument;
use DOMXPath;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\BlockRenderer;
use SaaSPlatform\ThemeRuntime\Security\HtmlSanitizer;
use Tests\TestCase;

/**
 * Work unit A (2026-10-30): a registry-wide sweep instead of one test per
 * block. Every `link` field, and every field whose key names a URL
 * (`*_url`, `*link`, `*href`), at the top level and inside repeater items
 * (one nested list level, ADR-061), is rendered through the canonical
 * renderer with each unsafe value below. No URL-bearing attribute, event
 * handler or style may carry an executable scheme afterwards.
 *
 * Found by it: hero_split/hero_gradient `button2_url` and hero_trust
 * `btn_primary_url`/`btn_secondary_url` went through escapeAttr(), and
 * isValidUrl() treated `java<TAB>script:` as a relative path although
 * browsers drop ASCII tab/newline before parsing a URL.
 */
class LinkFieldUnsafeSchemeSweepTest extends TestCase
{
    private const UNSAFE = [
        'javascript:alert(1)',
        'JaVaScRiPt:alert(1)',
        "  javascript:alert(1)",
        "\x01javascript:alert(1)",
        "java\tscript:alert(1)",
        "java\nscript:alert(1)",
        "jav\rascript:alert(1)",
        'vbscript:msgbox(1)',
        'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
    ];

    private const URL_ATTRIBUTES = ['href', 'src', 'action', 'formaction', 'poster', 'srcset', 'cite', 'background', 'data', 'xlink:href'];

    /** @return list<array{type:string, path:list<string>, storage:?string}> */
    private function targets(): array
    {
        $targets = [];
        foreach (BlockRegistry::all() as $type => $definition) {
            foreach ((array) ($definition['schema'] ?? []) as $key => $field) {
                $this->collect($targets, $type, [(string) $key], $field, null);
            }
        }
        return $targets;
    }

    private function collect(array &$targets, string $type, array $path, mixed $field, ?string $storage): void
    {
        if (!is_array($field)) {
            return;
        }
        $fieldType = $field['type'] ?? '';
        if ($fieldType === 'repeater' && is_array($field['fields'] ?? null)) {
            foreach ($field['fields'] as $sub => $subField) {
                $this->collect($targets, $type, [...$path, (string) $sub], $subField, $storage ?? ($field['storage'] ?? 'array'));
            }
            return;
        }
        $key = end($path);
        if ($fieldType === 'link' || (in_array($fieldType, ['string', 'text'], true) && preg_match('/(^|_)(url|link|href)$/i', $key))) {
            $targets[] = ['type' => $type, 'path' => $path, 'storage' => $storage];
        }
    }

    /**
     * Default props with the value placed at the target path (inside the
     * first item for repeaters). Empty text fields get a label first:
     * renderers omit a button whose text is empty, which would make its
     * URL field untestable (hero_trust's defaults are all empty).
     */
    private function propsWith(array $target, mixed $value): array
    {
        $props = BlockRegistry::defaultProps($target['type']);
        foreach ((array) (BlockRegistry::get($target['type'])['schema'] ?? []) as $key => $field) {
            if (is_array($field) && in_array($field['type'] ?? '', ['string', 'text', 'textarea'], true)
                && !preg_match('/(^|_)(url|link|href)$/i', (string) $key) && ($props[$key] ?? '') === '') {
                $props[$key] = 'متن نمونه';
            }
        }
        $path = $target['path'];
        if (count($path) === 1) {
            $props[$path[0]] = $value;
            return $props;
        }

        $listKey = array_shift($path);
        $items = $props[$listKey] ?? [];
        if (is_string($items)) {
            $items = json_decode($items, true) ?: [];
        }
        $items = is_array($items) ? array_values($items) : [];
        $items[0] = $this->setNested(is_array($items[0] ?? null) ? $items[0] : [], $path, $value);
        $props[$listKey] = $target['storage'] === 'json' ? json_encode($items, JSON_UNESCAPED_UNICODE) : $items;
        return $props;
    }

    private function setNested(array $item, array $path, mixed $value): array
    {
        $key = array_shift($path);
        if ($path === []) {
            $item[$key] = $value;
            return $item;
        }
        $children = is_array($item[$key] ?? null) ? array_values($item[$key]) : [];
        $children[0] = $this->setNested(is_array($children[0] ?? null) ? $children[0] : [], $path, $value);
        $item[$key] = $children;
        return $item;
    }

    private function render(array $target, mixed $value): string
    {
        return (new BlockRenderer())->renderBlock(['type' => $target['type'], 'id' => 'sweep', 'props' => $this->propsWith($target, $value)]);
    }

    /** @return list<string> attribute descriptions carrying an executable scheme */
    private function unsafeAttributes(string $html): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $found = [];
        foreach ((new DOMXPath($dom))->query('//@*') as $attr) {
            $name = strtolower($attr->nodeName);
            $isUrlBearing = in_array($name, self::URL_ATTRIBUTES, true)
                || str_starts_with($name, 'on')
                || $name === 'style'
                || preg_match('/(href|url|src)$/', $name);
            if (!$isUrlBearing) {
                continue;
            }
            // What a browser sees: control characters and spaces are dropped
            // from the scheme before it is matched.
            $normalized = strtolower(preg_replace('/[\x00-\x20]+/', '', $attr->nodeValue));
            if (str_contains($normalized, 'javascript:') || str_contains($normalized, 'vbscript:') || str_contains($normalized, 'data:text/html')) {
                $found[] = $attr->parentNode->nodeName . '[' . $name . ']=' . json_encode($attr->nodeValue);
            }
        }
        return $found;
    }

    public function test_the_sweep_covers_every_link_field_of_the_registry(): void
    {
        $targets = $this->targets();
        $linkFields = 0;
        foreach (BlockRegistry::all() as $definition) {
            foreach ((array) ($definition['schema'] ?? []) as $field) {
                $linkFields += (is_array($field) && ($field['type'] ?? '') === 'link') ? 1 : 0;
            }
        }

        $this->assertGreaterThanOrEqual($linkFields, count($targets), 'every top-level link field must be a sweep target');
        $this->assertContains(['type' => 'hero_gradient', 'path' => ['button2_url'], 'storage' => null], $targets);
        $this->assertContains(['type' => 'hero_trust', 'path' => ['btn_secondary_url'], 'storage' => null], $targets);
    }

    public function test_a_safe_url_reaches_the_output_so_the_sweep_is_not_vacuous(): void
    {
        $reached = 0;
        foreach ($this->targets() as $i => $target) {
            $probe = "https://probe.example/{$i}";
            if (str_contains($this->render($target, $probe), $probe)) {
                $reached++;
            }
        }
        // Some targets are legitimately not printed with default props
        // (e.g. a button whose text is empty); most must be.
        $this->assertGreaterThanOrEqual(intdiv(count($this->targets()), 2), $reached, "only {$reached} targets printed a safe probe URL");
    }

    public function test_no_link_field_renders_an_executable_scheme(): void
    {
        $failures = [];
        foreach ($this->targets() as $target) {
            foreach (self::UNSAFE as $payload) {
                foreach ($this->unsafeAttributes($this->render($target, $payload)) as $hit) {
                    $failures[] = $target['type'] . '.' . implode('.', $target['path']) . ' ← ' . json_encode($payload) . ' → ' . $hit;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($failures)));
    }

    /**
     * Canvas overhaul Unit 2b: a `link` field may hold an ADR-061 structured
     * {url, target} link. Renderers that passed it straight to escapeUrl()
     * (hero.button_url among them) threw a TypeError and the block became
     * an error placeholder. Only `link` controls: named-URL string fields
     * never hold the structured shape.
     */
    public function test_a_structured_link_never_breaks_a_block(): void
    {
        $failures = [];
        foreach ($this->targets() as $i => $target) {
            $field = BlockRegistry::get($target['type'])['schema'] ?? [];
            foreach ($target['path'] as $segment) {
                $field = is_array($field) ? ($field[$segment] ?? ($field['fields'][$segment] ?? null)) : null;
            }
            if (($field['type'] ?? '') !== 'link') {
                continue;
            }
            try {
                $html = $this->render($target, ['url' => "/structured-probe-{$i}", 'target' => '_blank']);
            } catch (\Throwable $e) {
                $failures[] = $target['type'] . '.' . implode('.', $target['path']) . ' → ' . $e->getMessage();
                continue;
            }
            if (preg_match('/\bArray\b(?![.(])/', $html) === 1) {
                $failures[] = $target['type'] . '.' . implode('.', $target['path']) . ' → printed "Array"';
            }
        }

        $this->assertSame([], $failures);
    }

    public function test_the_shared_sanitizer_drops_tab_and_newline_split_schemes(): void
    {
        foreach (["java\tscript:alert(1)", "java\nscript:alert(1)", "jav\rascript:alert(1)", "\tjava\nscript\r:alert(1)", "vb\tscript:x", "da\nta:text/html,x"] as $payload) {
            $this->assertFalse(HtmlSanitizer::isValidUrl($payload), json_encode($payload));
            $this->assertSame('#', HtmlSanitizer::escapeUrl($payload));
        }
        foreach (['/shop', '#faq', 'https://example.ir/a?b=1', 'tel:+982100000000', 'mailto:a@b.ir', 'about-us'] as $safe) {
            $this->assertTrue(HtmlSanitizer::isValidUrl($safe), $safe);
        }
    }
}
