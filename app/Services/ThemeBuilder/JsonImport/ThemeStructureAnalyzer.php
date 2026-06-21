<?php

namespace App\Services\ThemeBuilder\JsonImport;

/**
 * Step 1 of the JSON Import Pipeline.
 *
 * Detects the source builder format from the raw JSON and extracts a
 * lightweight metadata summary (section count, page count, etc.) used
 * by downstream steps for routing to the correct adapter.
 */
class ThemeStructureAnalyzer
{
    public const SOURCE_ELEMENTOR = 'elementor';
    public const SOURCE_BRICKS    = 'bricks';
    public const SOURCE_SHOPIFY   = 'shopify';
    public const SOURCE_WEBFLOW   = 'webflow';
    public const SOURCE_CUSTOM    = 'custom';

    public static function allSources(): array
    {
        return [
            self::SOURCE_ELEMENTOR => 'Elementor',
            self::SOURCE_BRICKS    => 'Bricks Builder',
            self::SOURCE_SHOPIFY   => 'Shopify Theme JSON',
            self::SOURCE_WEBFLOW   => 'Webflow Export',
            self::SOURCE_CUSTOM    => 'Custom Builder / ThemePack v1',
        ];
    }

    // ── Detection ─────────────────────────────────────────────────────────────

    public function detect(array $json): string
    {
        // Explicit type markers (most reliable)
        if (isset($json['type'])) {
            if ($json['type'] === 'elementor') return self::SOURCE_ELEMENTOR;
            if (str_contains(strtolower((string) $json['type']), 'webflow')) return self::SOURCE_WEBFLOW;
        }

        // Elementor: elType key in first content node
        if (isset($json['content'][0]['elType'])) return self::SOURCE_ELEMENTOR;
        if (isset($json[0]['elType']))             return self::SOURCE_ELEMENTOR;

        // Bricks: source marker or children key on content nodes
        if (isset($json['source']) && str_contains(strtolower((string) $json['source']), 'bricks')) {
            return self::SOURCE_BRICKS;
        }
        if (isset($json['content'][0]['type']) && isset($json['content'][0]['children'])) {
            return self::SOURCE_BRICKS;
        }
        if (isset($json['content'][0]['type']) && isset($json['content'][0]['settings']) && !isset($json['content'][0]['elType'])) {
            return self::SOURCE_BRICKS;
        }

        // Shopify: sections key with type sub-keys (single-page / sections.json)
        if (isset($json['sections']) && is_array($json['sections'])) {
            $first = reset($json['sections']);
            if (is_array($first) && isset($first['type'])) return self::SOURCE_SHOPIFY;
        }

        // Shopify multi-page export: pages array where each page has a 'sections' key
        if (isset($json['pages']) && is_array($json['pages']) && !empty($json['pages'])) {
            $firstPage = reset($json['pages']);
            if (is_array($firstPage) && isset($firstPage['sections'])) {
                return self::SOURCE_SHOPIFY;
            }
        }

        // Webflow: payload structure
        if (isset($json['payload']['site']) || isset($json['payload']['pages'])) return self::SOURCE_WEBFLOW;

        // Custom ThemePack v1
        if (isset($json['format']) && $json['format'] === 'theme-pack-v1') return self::SOURCE_CUSTOM;
        if (isset($json['pack_data'])) return self::SOURCE_CUSTOM;
        if (isset($json['global_styles']) && isset($json['pages'])) return self::SOURCE_CUSTOM;

        return self::SOURCE_CUSTOM;
    }

    // ── Analysis (metadata only, not full parse) ──────────────────────────────

    public function analyze(array $json, string $source): array
    {
        return match ($source) {
            self::SOURCE_ELEMENTOR => $this->analyzeElementor($json),
            self::SOURCE_BRICKS    => $this->analyzeBricks($json),
            self::SOURCE_SHOPIFY   => $this->analyzeShopify($json),
            self::SOURCE_WEBFLOW   => $this->analyzeWebflow($json),
            default                => $this->analyzeCustom($json),
        };
    }

    private function analyzeElementor(array $json): array
    {
        $content = $json['content'] ?? (isset($json[0]['elType']) ? $json : []);
        $sections = array_filter($content, fn($el) => ($el['elType'] ?? '') === 'section');
        return [
            'source'        => self::SOURCE_ELEMENTOR,
            'title'         => $json['title'] ?? '',
            'section_count' => count($sections),
            'widget_count'  => $this->countElementorWidgets($content),
            'has_header'    => false,
            'has_footer'    => false,
            'page_count'    => 1,
        ];
    }

    private function countElementorWidgets(array $elements): int
    {
        $count = 0;
        foreach ($elements as $el) {
            if (($el['elType'] ?? '') === 'widget') $count++;
            if (!empty($el['elements'])) $count += $this->countElementorWidgets($el['elements']);
        }
        return $count;
    }

    private function analyzeBricks(array $json): array
    {
        $content = $json['content'] ?? [];
        $sections = array_filter($content, fn($el) => ($el['type'] ?? '') === 'section');
        return [
            'source'        => self::SOURCE_BRICKS,
            'title'         => $json['title'] ?? '',
            'section_count' => count($sections),
            'widget_count'  => count($content),
            'has_header'    => !empty($json['header']),
            'has_footer'    => !empty($json['footer']),
            'page_count'    => 1,
        ];
    }

    private function analyzeShopify(array $json): array
    {
        $sections = $json['sections'] ?? [];
        $blockCount = array_sum(array_map(fn($s) => count($s['blocks'] ?? []), $sections));
        return [
            'source'        => self::SOURCE_SHOPIFY,
            'title'         => $json['name'] ?? $json['title'] ?? 'Shopify Theme',
            'section_count' => count($sections),
            'widget_count'  => $blockCount,
            'has_header'    => isset($sections['header']),
            'has_footer'    => isset($sections['footer']),
            'page_count'    => max(1, count($json['pages'] ?? [])),
        ];
    }

    private function analyzeWebflow(array $json): array
    {
        $payload = $json['payload'] ?? $json;
        $pages   = $payload['pages'] ?? [];
        return [
            'source'        => self::SOURCE_WEBFLOW,
            'title'         => $payload['site']['name'] ?? $json['name'] ?? 'Webflow Site',
            'section_count' => 0,
            'widget_count'  => 0,
            'has_header'    => true,
            'has_footer'    => true,
            'page_count'    => max(1, count($pages)),
        ];
    }

    private function analyzeCustom(array $json): array
    {
        $packData = $json['pack_data'] ?? $json;
        return [
            'source'        => self::SOURCE_CUSTOM,
            'title'         => $json['name'] ?? 'Custom Theme',
            'section_count' => count($packData['pages'] ?? []),
            'widget_count'  => 0,
            'has_header'    => !empty($packData['headers']),
            'has_footer'    => !empty($packData['footers']),
            'page_count'    => max(1, count($packData['pages'] ?? [])),
        ];
    }
}
