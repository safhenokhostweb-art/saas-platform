<?php

namespace Tests\Unit\ThemeBuilder;

use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\BlockRenderer;
use Tests\TestCase;
use Throwable;

/**
 * Canvas overhaul Unit 2d: a registry-wide sweep over every `image`
 * control, at the top level and inside repeater items at any depth.
 *
 * A Media Library pick is stored as {"media_id": "<uuid>"}. The host
 * resolves it to a URL before rendering, but when one reaches a renderer
 * unresolved (deleted media, a render path without a resolver) the block
 * must still render with "no image", not raise "Array to string
 * conversion" and become an error placeholder. Production hit exactly
 * that on bento_grid items[].bg_image and nav_header's mega banner
 * (customer-site log, 2026-10-30/31).
 */
class MediaFieldUnresolvedSweepTest extends TestCase
{
    private const MEDIA = ['media_id' => '00000000-0000-4000-8000-00000000c0de'];

    /** @return list<array{type:string, path:list<string>}> */
    private function targets(): array
    {
        $targets = [];
        foreach (BlockRegistry::all() as $type => $definition) {
            $this->collect($targets, (string) $type, [], (array) ($definition['schema'] ?? []));
        }
        return $targets;
    }

    private function collect(array &$targets, string $type, array $prefix, array $schema): void
    {
        foreach ($schema as $key => $field) {
            if (!is_array($field)) {
                continue;
            }
            $path = [...$prefix, (string) $key];
            if (($field['type'] ?? '') === 'image') {
                $targets[] = ['type' => $type, 'path' => $path];
            }
            if (($field['type'] ?? '') === 'repeater' && is_array($field['fields'] ?? null)) {
                $this->collect($targets, $type, $path, $field['fields']);
            }
        }
    }

    /** Default props with $value at the target path, inside the first item of each repeater level. */
    private function propsWith(array $target, mixed $value): array
    {
        return $this->place(BlockRegistry::defaultProps($target['type']), $target['path'], $value);
    }

    private function place(array $node, array $path, mixed $value): array
    {
        $key = array_shift($path);
        if ($path === []) {
            $node[$key] = $value;
            return $node;
        }
        $raw = $node[$key] ?? [];
        $wasJson = is_string($raw);
        $items = $wasJson ? (json_decode($raw, true) ?: []) : (is_array($raw) ? array_values($raw) : []);
        $items[0] = $this->place(is_array($items[0] ?? null) ? $items[0] : [], $path, $value);
        $node[$key] = $wasJson ? json_encode($items, JSON_UNESCAPED_UNICODE) : $items;
        return $node;
    }

    private function render(array $target, mixed $value): string
    {
        return (new BlockRenderer())->renderBlock(['type' => $target['type'], 'id' => 'sweep', 'props' => $this->propsWith($target, $value)]);
    }

    private function label(array $target): string
    {
        return $target['type'] . ':' . implode('.', $target['path']);
    }

    public function test_the_sweep_reaches_nested_image_fields(): void
    {
        $labels = array_map(fn ($t) => $this->label($t), $this->targets());

        $this->assertContains('bento_grid:items.bg_image', $labels);
        $this->assertContains('nav_header:nav_links.mega_banner_image', $labels);
        $this->assertContains('nav_header:nav_links.mega_columns.items.image', $labels);
    }

    public function test_an_unresolved_media_value_never_breaks_a_block(): void
    {
        $failures = [];
        foreach ($this->targets() as $target) {
            try {
                $html = $this->render($target, self::MEDIA);
            } catch (Throwable $e) {
                $failures[] = $this->label($target) . ' → ' . $e->getMessage();
                continue;
            }
            // PHP's "Array" string, not inline JS such as Array.prototype.
            if (preg_match('/\bArray\b(?![.(])/', $html) === 1 || str_contains($html, self::MEDIA['media_id'])) {
                $failures[] = $this->label($target) . ' → leaked the raw value into the markup';
            }
        }

        $this->assertSame([], $failures);
    }

    public function test_a_url_at_the_same_path_reaches_the_output_so_the_sweep_is_not_vacuous(): void
    {
        $targets = $this->targets();
        $reached = 0;
        foreach ($targets as $i => $target) {
            $probe = "/storage/sweep-probe-{$i}.webp";
            try {
                $reached += str_contains($this->render($target, $probe), $probe) ? 1 : 0;
            } catch (Throwable) {
                // Counted as not reached; the test above reports crashes.
            }
        }

        $this->assertGreaterThanOrEqual(intdiv(count($targets), 2), $reached, "only {$reached} of " . count($targets) . ' image fields printed a probe URL');
    }
}
