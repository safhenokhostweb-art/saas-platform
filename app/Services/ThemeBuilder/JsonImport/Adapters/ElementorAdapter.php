<?php

namespace App\Services\ThemeBuilder\JsonImport\Adapters;

use App\Services\ThemeBuilder\JsonImport\WidgetMapper;
use Illuminate\Support\Str;

/**
 * Converts Elementor JSON export to normalized ThemeBuilder structure.
 *
 * Elementor layout:
 *   content[] → sections (elType=section)
 *     └─ elements[] → columns (elType=column)
 *           └─ elements[] → widgets (elType=widget, widgetType=...)
 *
 * Elementor Pro inner sections are handled recursively.
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
            if ($elType === 'section') {
                $blocks[] = $this->processSection($el);
            } elseif ($elType === 'widget') {
                $block = $this->processWidget($el);
                if ($block) $blocks[] = $block;
            }
        }
        return $blocks;
    }

    private function processSection(array $section): array
    {
        $settings = $section['settings'] ?? [];
        $children = [];

        foreach ($section['elements'] ?? [] as $column) {
            if (($column['elType'] ?? '') !== 'column') continue;

            $colWidgets = [];
            foreach ($column['elements'] ?? [] as $widget) {
                $wElType = $widget['elType'] ?? '';
                if ($wElType === 'widget') {
                    $block = $this->processWidget($widget);
                    if ($block) $colWidgets[] = $block;
                } elseif ($wElType === 'section') {
                    // Elementor Pro inner section
                    $colWidgets[] = $this->processSection($widget);
                }
            }
            $children = array_merge($children, $colWidgets);
        }

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
            $firstChild = $block['blocks'][0] ?? [];
            $childType  = $firstChild['type'] ?? '';

            // Heuristic: a section whose only or first child is nav/logo = header
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
