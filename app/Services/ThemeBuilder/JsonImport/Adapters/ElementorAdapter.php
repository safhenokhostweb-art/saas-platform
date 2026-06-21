<?php

namespace App\Services\ThemeBuilder\JsonImport\Adapters;

use App\Services\ThemeBuilder\JsonImport\WidgetMapper;
use Illuminate\Support\Str;

/**
 * Converts Elementor JSON export to normalized ThemeBuilder structure.
 *
 * Supports both layout generations:
 *   Legacy (≤3.5): content[] → section → column → widget
 *   Flexbox (≥3.6): content[] → container → container* → widget
 *
 * Third-party widget types (ElementsKit, JetElements, etc.) are mapped
 * to their nearest built-in equivalent by WidgetMapper fallback rules.
 */
class ElementorAdapter
{
    public function __construct(private readonly WidgetMapper $mapper) {}

    /**
     * @return array {
     *   pages: array,
     *   global_styles: array,
     *   header_blocks: array,
     *   footer_blocks: array,
     *   meta: array,
     * }
     */
    public function convert(array $json): array
    {
        // Support both single-page object and raw array export
        $content = $json['content'] ?? (isset($json[0]['elType']) ? $json : []);
        $title   = $json['title'] ?? 'صفحه اصلی';

        $globalStyles  = $this->extractGlobalStyles($json);
        $blocks        = $this->processElements($content);

        // Separate header/footer sections if detected
        [$headerBlocks, $footerBlocks, $bodyBlocks] = $this->splitLayoutSections($blocks);

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
                'source'  => 'elementor',
                'version' => $json['version'] ?? '',
                'title'   => $title,
            ],
        ];
    }

    // ── Element processing ────────────────────────────────────────────────────

    private function processElements(array $elements): array
    {
        $blocks = [];
        foreach ($elements as $el) {
            $elType = $el['elType'] ?? '';

            // Legacy section or new Flexbox container at root level
            if ($elType === 'section' || $elType === 'container') {
                $block = $this->processContainer($el);
                if ($block) $blocks[] = $block;
            } elseif ($elType === 'widget') {
                $block = $this->processWidget($el);
                if ($block) $blocks[] = $block;
            }
        }
        return $blocks;
    }

    /**
     * Handles both legacy section/column and Elementor 3.6+ container layout.
     * Containers may nest arbitrarily; widgets are extracted recursively.
     */
    private function processContainer(array $el): ?array
    {
        $settings = $el['settings'] ?? [];
        $elType   = $el['elType'] ?? '';
        $children = [];

        foreach ($el['elements'] ?? [] as $child) {
            $childType = $child['elType'] ?? '';

            if ($childType === 'widget') {
                $block = $this->processWidget($child);
                if ($block) $children[] = $block;
            } elseif ($childType === 'column') {
                // Legacy: column wraps widgets
                foreach ($child['elements'] ?? [] as $widget) {
                    $wType = $widget['elType'] ?? '';
                    if ($wType === 'widget') {
                        $block = $this->processWidget($widget);
                        if ($block) $children[] = $block;
                    } elseif (in_array($wType, ['section', 'container'])) {
                        $inner = $this->processContainer($widget);
                        if ($inner) $children[] = $inner;
                    }
                }
            } elseif (in_array($childType, ['section', 'container'])) {
                // Nested container — flatten widgets up one level so sections render
                $inner = $this->processContainer($child);
                if ($inner) {
                    // If inner has no meaningful block type, promote its children
                    $innerChildren = $inner['blocks'] ?? [];
                    if (!empty($innerChildren)) {
                        $children = array_merge($children, $innerChildren);
                    }
                }
            }
        }

        // Skip containers that yielded no renderable children
        if (empty($children)) return null;

        $bgColor = $settings['background_color'] ?? '';
        $bgImage = $settings['background_image']['url'] ?? '';

        return [
            'id'    => Str::random(8),
            'type'  => 'section',
            'props' => [
                'padding'          => $this->mapPadding($settings['padding'] ?? []),
                'background'       => $bgColor ?: 'transparent',
                'background_image' => $bgImage,
                'container'        => true,
                'blocks'           => $children,
            ],
            'blocks' => $children,
        ];
    }

    private function processWidget(array $widget): ?array
    {
        $widgetType = $widget['widgetType'] ?? '';
        if (empty($widgetType)) return null;

        $settings = $widget['settings'] ?? [];
        return $this->mapper->mapNode('elementor', $widgetType, $settings);
    }

    // ── Layout separation (header / footer / body) ────────────────────────────

    private function splitLayoutSections(array $blocks): array
    {
        $header = [];
        $footer = [];
        $body   = [];

        foreach ($blocks as $block) {
            // Check all children (not just first) for nav/logo heuristics
            $allChildren = $block['blocks'] ?? [];
            $firstChild  = $allChildren[0] ?? [];
            $childType   = $firstChild['type'] ?? '';

            // Heuristic: a section whose first child looks like nav = header
            if (in_array($childType, ['heading']) && $this->looksLikeNav($firstChild)) {
                $header[] = $block;
            } else {
                $body[] = $block;
            }
        }

        // Last body section becomes footer if it matches footer heuristic
        if (!empty($body)) {
            $last = end($body);
            $lastType = ($last['blocks'][0]['type'] ?? '');
            if ($this->looksLikeFooter($last)) {
                $footer[] = array_pop($body);
            }
        }

        return [$header, $footer, $body];
    }

    private function looksLikeNav(array $block): bool
    {
        return str_contains(strtolower($block['props']['text'] ?? ''), 'menu')
            || str_contains(strtolower($block['props']['text'] ?? ''), 'nav');
    }

    private function looksLikeFooter(array $block): bool
    {
        foreach ($block['blocks'] ?? [] as $child) {
            if (str_contains(strtolower($child['props']['content'] ?? ''), 'copyright')
                || str_contains(strtolower($child['props']['content'] ?? ''), '©')) {
                return true;
            }
        }
        return false;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function mapPadding(array $padding): string
    {
        $top = (int) ($padding['top'] ?? 40);
        if ($top < 20) return 'sm';
        if ($top < 60) return 'md';
        if ($top < 100) return 'lg';
        return 'xl';
    }

    private function extractGlobalStyles(array $json): array
    {
        $pageSettings = $json['page_settings'] ?? [];
        $styles = [
            'primaryColor'    => '#6366f1',
            'secondaryColor'  => '#8b5cf6',
            'backgroundColor' => '#ffffff',
            'textColor'       => '#1f2937',
            'fontFamily'      => 'Vazirmatn',
            'direction'       => 'rtl',
        ];

        if (!empty($pageSettings['background_color'])) {
            $styles['backgroundColor'] = $pageSettings['background_color'];
        }

        return $styles;
    }
}
