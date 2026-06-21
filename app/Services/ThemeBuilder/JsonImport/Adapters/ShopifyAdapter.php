<?php

namespace App\Services\ThemeBuilder\JsonImport\Adapters;

use App\Services\ThemeBuilder\JsonImport\WidgetMapper;
use Illuminate\Support\Str;

/**
 * Converts Shopify Theme JSON (sections.json) to normalized ThemeBuilder structure.
 *
 * Shopify layout:
 *   sections: { [id]: { type, settings, blocks: { [id]: { type, settings } } } }
 *   order: [section_id, ...]
 *
 * Shopify also supports multi-page export via JSON templates:
 *   pages: [ { name, sections: {...}, order: [...] } ]
 */
class ShopifyAdapter
{
    // Shopify section types reserved for header/footer
    private const HEADER_TYPES = ['header', 'announcement-bar'];
    private const FOOTER_TYPES = ['footer'];

    public function __construct(private readonly WidgetMapper $mapper) {}

    public function convert(array $json): array
    {
        $title = $json['name'] ?? $json['title'] ?? 'Shopify Theme';

        // Multi-page export (array of page objects)
        if (isset($json['pages']) && is_array($json['pages'])) {
            return $this->convertMultiPage($json, $title);
        }

        // Single page / sections.json
        return $this->convertSinglePage($json, $title);
    }

    // ── Single page ───────────────────────────────────────────────────────────

    private function convertSinglePage(array $json, string $title): array
    {
        $sections = $json['sections'] ?? [];
        $order    = $json['order'] ?? array_keys($sections);

        [$headerBlocks, $footerBlocks, $bodyBlocks] = $this->convertSections($sections, $order);

        return [
            'pages' => [
                'home' => [
                    'title'  => $title,
                    'slug'   => '',
                    'blocks' => $bodyBlocks,
                ],
            ],
            'global_styles' => $this->extractGlobalStyles($json),
            'header_blocks' => $headerBlocks,
            'footer_blocks' => $footerBlocks,
            'meta' => [
                'source' => 'shopify',
                'title'  => $title,
            ],
        ];
    }

    // ── Multi-page ────────────────────────────────────────────────────────────

    private function convertMultiPage(array $json, string $title): array
    {
        $pages  = [];
        $header = [];
        $footer = [];

        foreach ($json['pages'] as $page) {
            $sections = $page['sections'] ?? [];
            $order    = $page['order'] ?? array_keys($sections);
            $slug     = Str::slug($page['name'] ?? 'page');
            $isHome   = $slug === 'index' || $slug === 'home' || empty($pages);

            [$h, $f, $body] = $this->convertSections($sections, $order);
            if (empty($header) && !empty($h)) $header = $h;
            if (empty($footer) && !empty($f)) $footer = $f;

            $key = $isHome ? 'home' : $slug;
            $pages[$key] = [
                'title'  => $page['name'] ?? 'صفحه',
                'slug'   => $isHome ? '' : $slug,
                'blocks' => $body,
            ];
        }

        return [
            'pages'         => $pages,
            'global_styles' => $this->extractGlobalStyles($json),
            'header_blocks' => $header,
            'footer_blocks' => $footer,
            'meta' => ['source' => 'shopify', 'title' => $title],
        ];
    }

    // ── Section conversion ────────────────────────────────────────────────────

    private function convertSections(array $sections, array $order): array
    {
        $header = [];
        $footer = [];
        $body   = [];

        foreach ($order as $sectionId) {
            $section = $sections[$sectionId] ?? null;
            if (!$section) continue;

            $sType    = $section['type'] ?? '';
            $settings = $section['settings'] ?? [];
            $subBlocks= $this->convertSectionBlocks($section);

            $block = $this->mapper->mapNode('shopify', $sType, $settings, $subBlocks);

            if (in_array($sType, self::HEADER_TYPES)) {
                $header[] = $block;
            } elseif (in_array($sType, self::FOOTER_TYPES)) {
                $footer[] = $block;
            } else {
                // Wrap in a section layout block for proper spacing
                $body[] = [
                    'id'    => Str::random(8),
                    'type'  => 'section',
                    'props' => ['padding' => 'lg', 'background' => 'transparent', 'container' => true, 'blocks' => [$block]],
                    'blocks'=> [$block],
                ];
            }
        }

        return [$header, $footer, $body];
    }

    private function convertSectionBlocks(array $section): array
    {
        $children = [];
        $blocks   = $section['blocks'] ?? [];
        $order    = $section['blocks_order'] ?? array_keys($blocks);

        foreach ($order as $blockId) {
            $b = $blocks[$blockId] ?? null;
            if (!$b) continue;

            $type     = $b['type'] ?? 'text';
            $settings = $b['settings'] ?? [];
            $children[] = $this->mapper->mapNode('shopify', $type, $settings);
        }

        return $children;
    }

    // ── Global styles ─────────────────────────────────────────────────────────

    private function extractGlobalStyles(array $json): array
    {
        $settings = $json['settings'] ?? $json['settings_data'] ?? [];
        return [
            'primaryColor'    => $settings['colors_accent_1'] ?? '#6366f1',
            'secondaryColor'  => $settings['colors_accent_2'] ?? '#8b5cf6',
            'backgroundColor' => $settings['colors_background_1'] ?? '#ffffff',
            'textColor'       => $settings['colors_text'] ?? '#1f2937',
            'fontFamily'      => 'Vazirmatn',
            'direction'       => 'rtl',
        ];
    }
}
