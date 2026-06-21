<?php

namespace App\Services\ThemeBuilder\JsonImport\Adapters;

use App\Services\ThemeBuilder\JsonImport\WidgetMapper;
use Illuminate\Support\Str;

/**
 * Converts Bricks Builder JSON export to normalized ThemeBuilder structure.
 *
 * Bricks layout (content array, flat with parent references OR nested children):
 *   content[] → elements { id, type, parent, settings, children[] }
 *
 * Bricks also exports global_elements (reusable templates) separately.
 */
class BricksAdapter
{
    public function __construct(private readonly WidgetMapper $mapper) {}

    public function convert(array $json): array
    {
        $content = $json['content'] ?? [];
        $header  = $json['header'] ?? [];
        $footer  = $json['footer'] ?? [];
        $title   = $json['title'] ?? 'صفحه اصلی';

        $globalStyles = $this->extractGlobalStyles($json);

        // Bricks can export as flat (with parent ids) or nested (children key)
        if ($this->isNestedFormat($content)) {
            $bodyBlocks   = $this->processNested($content);
            $headerBlocks = $this->processNested($header);
            $footerBlocks = $this->processNested($footer);
        } else {
            $bodyBlocks   = $this->processFlat($content);
            $headerBlocks = $this->processFlat($header);
            $footerBlocks = $this->processFlat($footer);
        }

        return [
            'pages' => [
                'home' => [
                    'title'  => $title,
                    'slug'   => '',
                    'blocks' => $bodyBlocks,
                ],
            ],
            'global_styles' => $globalStyles,
            'header_blocks' => $headerBlocks,
            'footer_blocks' => $footerBlocks,
            'meta' => [
                'source'  => 'bricks',
                'version' => $json['version'] ?? '',
                'title'   => $title,
            ],
        ];
    }

    // ── Nested format (children key present) ──────────────────────────────────

    private function isNestedFormat(array $content): bool
    {
        return !empty($content) && isset($content[0]['children']);
    }

    private function processNested(array $elements): array
    {
        $blocks = [];
        foreach ($elements as $el) {
            $type     = $el['type'] ?? '';
            $settings = $el['settings'] ?? [];
            $children = $this->processNested($el['children'] ?? []);

            if (empty($type)) continue;

            // skip pure layout wrappers with no settings — flatten children up
            if (in_array($type, ['div', 'block']) && empty($settings) && !empty($children)) {
                $blocks = array_merge($blocks, $children);
                continue;
            }

            $blocks[] = $this->mapper->mapNode('bricks', $type, $settings, $children);
        }
        return $blocks;
    }

    // ── Flat format (parent ids, rebuild tree) ────────────────────────────────

    private function processFlat(array $elements): array
    {
        if (empty($elements)) return [];

        // Index by id
        $index = [];
        foreach ($elements as $el) {
            $id = $el['id'] ?? Str::random(8);
            $index[$id] = $el;
        }

        // Find roots (no parent or parent = 0)
        $roots = array_filter($elements, fn($el) => empty($el['parent']) || $el['parent'] === 0);

        return $this->buildTree(array_values($roots), $index);
    }

    private function buildTree(array $roots, array &$index): array
    {
        $blocks = [];
        foreach ($roots as $el) {
            $id       = $el['id'] ?? '';
            $type     = $el['type'] ?? '';
            $settings = $el['settings'] ?? [];

            // Find children
            $childEls = array_filter($index, fn($e) => ($e['parent'] ?? '') === $id);
            $children = $this->buildTree(array_values($childEls), $index);

            if (empty($type)) continue;
            $blocks[] = $this->mapper->mapNode('bricks', $type, $settings, $children);
        }
        return $blocks;
    }

    // ── Global styles ─────────────────────────────────────────────────────────

    private function extractGlobalStyles(array $json): array
    {
        $styles  = $json['styles'] ?? $json['globalStyles'] ?? [];
        $primary = '#6366f1';

        foreach ($styles as $s) {
            if (!empty($s['settings']['color'])) {
                $primary = $s['settings']['color'];
                break;
            }
        }

        return [
            'primaryColor'    => $primary,
            'secondaryColor'  => '#8b5cf6',
            'backgroundColor' => '#ffffff',
            'textColor'       => '#1f2937',
            'fontFamily'      => 'Vazirmatn',
            'direction'       => 'rtl',
        ];
    }
}
