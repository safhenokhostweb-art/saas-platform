<?php

namespace App\Services\ThemeBuilder\JsonImport;

use App\Models\Theme;
use App\Models\ThemeMarketplace;
use App\Models\ThemePack;
use Illuminate\Support\Str;

/**
 * Step 4 of the JSON Import Pipeline.
 *
 * Receives the normalized structure from an adapter and produces:
 *   1. A ThemePack (always)
 *   2. Optionally a Theme + ThemeMarketplace entry (when publishToMarketplace = true)
 *
 * Input normalized structure:
 * {
 *   pages: { key: { title, slug, blocks[] } },
 *   global_styles: {...},
 *   header_blocks: [...],
 *   footer_blocks: [...],
 *   meta: { source, title, version, ... },
 * }
 */
class ThemePackGenerator
{
    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Generate a ThemePack from normalized adapter output.
     *
     * @param  array  $structure   Output from adapter::convert()
     * @param  string $name        User-supplied theme name
     * @param  array  $options     { category, description, publish_to_marketplace }
     * @return array {
     *   theme_pack: ThemePack,
     *   theme: ?Theme,
     *   stats: array,
     *   quality: array,
     * }
     */
    public function generate(array $structure, string $name, array $options = []): array
    {
        $pages       = $structure['pages'] ?? ['home' => ['title' => 'صفحه اصلی', 'slug' => '', 'blocks' => []]];
        $globalStyles= $structure['global_styles'] ?? [];
        $headerBlocks= $structure['header_blocks'] ?? [];
        $footerBlocks= $structure['footer_blocks'] ?? [];
        $meta        = $structure['meta'] ?? [];
        $source      = $meta['source'] ?? 'custom';
        $version     = $meta['version'] ?? '1.0.0';
        $category    = $options['category'] ?? Theme::CATEGORY_CORPORATE;
        $description = $options['description'] ?? "وارد شده از {$this->sourceLabel($source)}";

        // Gather all blocks across pages for stats
        $stats = $this->computeStats($pages, $headerBlocks, $footerBlocks);

        // Validate block IDs (ensure uniqueness)
        $pages        = $this->normalizePageIds($pages);
        $headerBlocks = $this->normalizeBlockIds($headerBlocks);
        $footerBlocks = $this->normalizeBlockIds($footerBlocks);

        // Build ThemePack
        $packData = [
            'global_styles' => $globalStyles,
            'headers'       => $headerBlocks ? [$headerBlocks] : [],
            'footers'       => $footerBlocks ? [$footerBlocks] : [],
            'pages'         => $pages,
            'templates'     => [],
            'demo_data'     => [],
            'import_meta'   => [
                'source'        => $source,
                'source_label'  => $this->sourceLabel($source),
                'original_title'=> $meta['title'] ?? $name,
                'imported_at'   => now()->toISOString(),
                'stats'         => $stats,
            ],
        ];

        $slug = Str::slug($name) . '-' . Str::random(6);

        $pack = ThemePack::create([
            'uuid'             => (string) Str::uuid(),
            'name'             => $name,
            'description'      => $description,
            'version'          => is_string($version) ? $version : '1.0.0',
            'author'           => 'JSON Import Engine',
            'license_type'     => 'free',
            'tags'             => [$source, 'imported', $category],
            'pack_data'        => $packData,
            'is_public'        => false,
            'is_active'        => true,
            'site_id'          => null,
        ]);

        // Optionally publish to marketplace as a Theme
        $theme = null;
        if (!empty($options['publish_to_marketplace'])) {
            $theme = $this->publishAsTheme($name, $slug, $description, $category, $pages, $globalStyles, $headerBlocks, $footerBlocks, $source);
        }

        $quality = $this->computeQuality($stats, $pages);

        return compact('pack', 'theme', 'stats', 'quality');
    }

    // ── Marketplace publishing ────────────────────────────────────────────────

    private function publishAsTheme(
        string $name, string $slug, string $description,
        string $category, array $pages,
        array $globalStyles, array $headerBlocks, array $footerBlocks,
        string $source
    ): Theme {
        $theme = Theme::create([
            'name'          => $name,
            'slug'          => $slug,
            'description'   => $description,
            'category'      => $category,
            'source'        => Theme::SOURCE_GENERATOR,
            'type'          => Theme::TYPE_FULL,
            'status'        => Theme::STATUS_DRAFT,
            'site_id'       => null,
            'pages'         => $pages,
            'global_styles' => $globalStyles,
            'header_blocks' => $headerBlocks,
            'footer_blocks' => $footerBlocks,
            'settings'      => ['imported_from' => $source],
            'is_marketplace'=> true,
            'license_type'  => Theme::LICENSE_FREE,
            'version'       => 1,
        ]);

        // Use DB::table to bypass Eloquent mass-assignment/cast when inserting 0 values.
        // annual_price/lifetime_price have NOT NULL DEFAULT 0 in the DB schema.
        if (!\DB::table('theme_marketplace')->where('theme_id', $theme->id)->exists()) {
            \DB::table('theme_marketplace')->insert([
                'theme_id'       => $theme->id,
                'annual_price'   => 0,
                'lifetime_price' => 0,
                'is_featured'    => 0,
                'preview_url'    => '',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        return $theme;
    }

    // ── Stats & quality ───────────────────────────────────────────────────────

    private function computeStats(array $pages, array $header, array $footer): array
    {
        $totalBlocks  = 0;
        $blockTypes   = [];

        foreach ($pages as $page) {
            $blocks = $page['blocks'] ?? [];
            $totalBlocks += $this->countBlocks($blocks, $blockTypes);
        }
        $totalBlocks += $this->countBlocks($header, $blockTypes);
        $totalBlocks += $this->countBlocks($footer, $blockTypes);

        return [
            'page_count'         => count($pages),
            'total_blocks'       => $totalBlocks,
            'block_type_variety' => count($blockTypes),
            'block_types'        => $blockTypes,
            'has_header'         => !empty($header),
            'has_footer'         => !empty($footer),
        ];
    }

    private function countBlocks(array $blocks, array &$types): int
    {
        $count = 0;
        foreach ($blocks as $block) {
            $count++;
            $type = $block['type'] ?? 'unknown';
            $types[$type] = ($types[$type] ?? 0) + 1;
            if (!empty($block['blocks'])) {
                $count += $this->countBlocks($block['blocks'], $types);
            }
        }
        return $count;
    }

    private function computeQuality(array $stats, array $pages): array
    {
        $score    = 0;
        $findings = [];

        // Page coverage (max 25)
        $pc = min($stats['page_count'], 5);
        $score += $pc * 5;
        if ($pc < 3) $findings[] = ['type' => 'warning', 'message' => "فقط {$stats['page_count']} صفحه وارد شد — حداقل ۳ صفحه توصیه می‌شود"];

        // Block variety (max 25)
        $bv = min($stats['block_type_variety'], 5);
        $score += $bv * 5;
        if ($bv < 3) $findings[] = ['type' => 'warning', 'message' => 'تنوع بلوک کم است — قالب ممکن است ساده باشد'];

        // Has header + footer (max 20)
        if ($stats['has_header']) $score += 10;
        else $findings[] = ['type' => 'info', 'message' => 'هدر شناسایی نشد'];
        if ($stats['has_footer']) $score += 10;
        else $findings[] = ['type' => 'info', 'message' => 'فوتر شناسایی نشد'];

        // Block count (max 20)
        $bc = min($stats['total_blocks'], 20);
        $score += $bc;

        // Home page has content (max 10)
        $homeBlocks = count($pages['home']['blocks'] ?? []);
        if ($homeBlocks >= 5) $score += 10;
        elseif ($homeBlocks >= 2) $score += 5;
        else $findings[] = ['type' => 'warning', 'message' => 'صفحه اصلی بلوک کافی ندارد'];

        $score = min(100, $score);
        $grade = $score >= 80 ? 'EXCELLENT' : ($score >= 60 ? 'PASS' : ($score >= 40 ? 'PARTIAL' : 'FAIL'));

        return compact('score', 'grade', 'findings');
    }

    // ── ID normalization ──────────────────────────────────────────────────────

    private function normalizePageIds(array $pages): array
    {
        foreach ($pages as $key => &$page) {
            $page['blocks'] = $this->normalizeBlockIds($page['blocks'] ?? []);
        }
        return $pages;
    }

    private function normalizeBlockIds(array $blocks): array
    {
        $seen = [];
        return $this->deduplicateIds($blocks, $seen);
    }

    private function deduplicateIds(array $blocks, array &$seen): array
    {
        foreach ($blocks as &$block) {
            $id = $block['id'] ?? '';
            if (empty($id) || isset($seen[$id])) {
                $block['id'] = Str::random(8);
            }
            $seen[$block['id']] = true;

            if (!empty($block['blocks'])) {
                $block['blocks'] = $this->deduplicateIds($block['blocks'], $seen);
            }
            if (!empty($block['props']['blocks'])) {
                $block['props']['blocks'] = $this->deduplicateIds($block['props']['blocks'], $seen);
            }
        }
        return $blocks;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function sourceLabel(string $source): string
    {
        return [
            'elementor' => 'Elementor',
            'bricks'    => 'Bricks Builder',
            'shopify'   => 'Shopify',
            'webflow'   => 'Webflow',
            'custom'    => 'Custom Builder',
        ][$source] ?? $source;
    }
}
