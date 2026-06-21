<?php

namespace App\Services\ThemeBuilder\JsonImport\Adapters;

use Illuminate\Support\Str;

/**
 * Handles:
 *   - ThemePack v1 (our own export format from TB3 Sprint 6)
 *   - Generic custom builder JSON (best-effort mapping)
 *
 * ThemePack v1 schema:
 * {
 *   format: "theme-pack-v1",
 *   name, description, version, author,
 *   pack_data: {
 *     global_styles: {...},
 *     headers: [ [block, ...] ],
 *     footers: [ [block, ...] ],
 *     pages: { [key]: { title, slug, blocks: [...] } },
 *     templates: [...],
 *     demo_data: {...}
 *   }
 * }
 */
class CustomBuilderAdapter
{
    public function convert(array $json): array
    {
        // ThemePack v1 format
        if (isset($json['format']) && $json['format'] === 'theme-pack-v1') {
            return $this->convertThemePackV1($json);
        }

        // Direct pack_data structure
        if (isset($json['pack_data'])) {
            return $this->convertThemePackV1($json);
        }

        // Flat pages structure (our canvas export)
        if (isset($json['pages']) && isset($json['global_styles'])) {
            return [
                'pages'         => $json['pages'],
                'global_styles' => $json['global_styles'],
                'header_blocks' => $json['header_blocks'] ?? [],
                'footer_blocks' => $json['footer_blocks'] ?? [],
                'meta'          => ['source' => 'custom', 'title' => $json['name'] ?? 'Custom Theme'],
            ];
        }

        // Unknown custom format — extract whatever we can
        return $this->convertGeneric($json);
    }

    // ── ThemePack v1 ─────────────────────────────────────────────────────────

    private function convertThemePackV1(array $json): array
    {
        $data = $json['pack_data'] ?? [];
        $name = $json['name'] ?? 'Custom Theme';

        $pages = $data['pages'] ?? [];

        // If pages is indexed (from TB3 seed), convert to associative
        if (!empty($pages) && isset($pages[0])) {
            $converted = [];
            foreach ($pages as $page) {
                $key = Str::slug($page['title'] ?? 'page');
                if (empty($converted)) $key = 'home';
                $converted[$key] = $page;
            }
            $pages = $converted;
        }

        if (empty($pages)) {
            $pages = ['home' => ['title' => 'صفحه اصلی', 'slug' => '', 'blocks' => []]];
        }

        return [
            'pages'         => $pages,
            'global_styles' => $data['global_styles'] ?? $this->defaultGlobalStyles(),
            'header_blocks' => $data['headers'][0] ?? [],
            'footer_blocks' => $data['footers'][0] ?? [],
            'meta' => [
                'source'  => 'custom',
                'title'   => $name,
                'version' => $json['version'] ?? '1.0.0',
                'author'  => $json['author'] ?? '',
            ],
        ];
    }

    // ── Generic unknown format ────────────────────────────────────────────────

    private function convertGeneric(array $json): array
    {
        $title  = $json['title'] ?? $json['name'] ?? 'Custom Theme';
        $blocks = [];

        // Try to find blocks in various keys
        foreach (['blocks', 'sections', 'content', 'elements', 'components'] as $key) {
            if (!empty($json[$key]) && is_array($json[$key])) {
                $blocks = $this->normalizeGenericBlocks($json[$key]);
                break;
            }
        }

        return [
            'pages' => [
                'home' => ['title' => $title, 'slug' => '', 'blocks' => $blocks],
            ],
            'global_styles' => $this->defaultGlobalStyles(),
            'header_blocks' => [],
            'footer_blocks' => [],
            'meta' => ['source' => 'custom', 'title' => $title],
        ];
    }

    private function normalizeGenericBlocks(array $items): array
    {
        $blocks = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $type    = $item['type'] ?? 'text';
            $props   = $item['props'] ?? $item['settings'] ?? $item['data'] ?? [];
            $children= isset($item['blocks']) ? $this->normalizeGenericBlocks($item['blocks']) : [];

            $blocks[] = [
                'id'    => Str::random(8),
                'type'  => $type,
                'props' => $props,
                'blocks'=> $children,
            ];
        }
        return $blocks;
    }

    // ── Defaults ──────────────────────────────────────────────────────────────

    private function defaultGlobalStyles(): array
    {
        return [
            'primaryColor'    => '#6366f1',
            'secondaryColor'  => '#8b5cf6',
            'backgroundColor' => '#ffffff',
            'textColor'       => '#1f2937',
            'fontFamily'      => 'Vazirmatn',
            'direction'       => 'rtl',
        ];
    }
}
