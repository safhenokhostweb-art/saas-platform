<?php

namespace App\Services\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeMarketplace;

/**
 * GAP-012: Demo data manifest system.
 * Scans theme blocks to auto-detect required demo content types,
 * and manages the demo_data_config manifest stored in theme_marketplace.
 */
class ThemeDemoDataService
{
    // Block types that require specific content categories
    private const BLOCK_CONTENT_MAP = [
        'product-grid'   => ['type' => 'products',  'label' => 'محصولات', 'count' => 6,  'icon' => '🛍️'],
        'product-card'   => ['type' => 'products',  'label' => 'محصولات', 'count' => 3,  'icon' => '🛍️'],
        'shop-hero'      => ['type' => 'products',  'label' => 'محصولات', 'count' => 1,  'icon' => '🛍️'],
        'blog-list'      => ['type' => 'posts',     'label' => 'مقالات',  'count' => 6,  'icon' => '📝'],
        'blog-card'      => ['type' => 'posts',     'label' => 'مقالات',  'count' => 3,  'icon' => '📝'],
        'latest-posts'   => ['type' => 'posts',     'label' => 'مقالات',  'count' => 3,  'icon' => '📝'],
        'courses-grid'   => ['type' => 'courses',   'label' => 'دوره‌ها', 'count' => 4,  'icon' => '🎓'],
        'course-card'    => ['type' => 'courses',   'label' => 'دوره‌ها', 'count' => 3,  'icon' => '🎓'],
        'hero'           => ['type' => 'site_info', 'label' => 'اطلاعات سایت', 'count' => 1, 'icon' => '🏠'],
        'testimonials'   => ['type' => 'reviews',   'label' => 'نظرات',   'count' => 4,  'icon' => '⭐'],
        'team'           => ['type' => 'team',      'label' => 'تیم',     'count' => 4,  'icon' => '👥'],
        'faq'            => ['type' => 'faq',       'label' => 'سوالات',  'count' => 5,  'icon' => '❓'],
        'gallery'        => ['type' => 'gallery',   'label' => 'گالری',   'count' => 8,  'icon' => '🖼️'],
        'portfolio'      => ['type' => 'portfolio', 'label' => 'پورتفولیو', 'count' => 6, 'icon' => '💼'],
    ];

    public function buildManifestFromTheme(Theme $theme): array
    {
        $detected = [];
        $allBlocks = array_merge(
            $theme->header_blocks ?? [],
            $theme->footer_blocks ?? [],
            ...array_column($theme->pages ?? [], 'blocks')
        );

        foreach ($allBlocks as $block) {
            $type = $block['type'] ?? null;
            if ($type && isset(self::BLOCK_CONTENT_MAP[$type])) {
                $info = self::BLOCK_CONTENT_MAP[$type];
                $key  = $info['type'];
                if (!isset($detected[$key])) {
                    $detected[$key] = $info;
                } else {
                    $detected[$key]['count'] = max($detected[$key]['count'], $info['count']);
                }
            }
        }

        return [
            'detected_content' => array_values($detected),
            'total_items'      => array_sum(array_column($detected, 'count')),
            'generated_at'     => now()->toIsoString(),
        ];
    }

    public function saveManifest(Theme $theme, ?array $overrides = null): array
    {
        $manifest = $this->buildManifestFromTheme($theme);
        if ($overrides) {
            $manifest = array_merge($manifest, $overrides);
        }

        $meta = ThemeMarketplace::where('theme_id', $theme->id)->first();
        if ($meta) {
            $meta->update([
                'demo_data_config' => $manifest,
                'has_demo_data'    => count($manifest['detected_content']) > 0,
            ]);
        }

        return $manifest;
    }

    public function getManifest(Theme $theme): ?array
    {
        $meta = ThemeMarketplace::where('theme_id', $theme->id)->first();
        return $meta?->demo_data_config;
    }
}
