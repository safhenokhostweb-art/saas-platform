<?php

namespace App\Services\ThemeBuilder;

use App\Models\Theme;
use App\Models\ThemeMarketplace;

/**
 * Validates marketplace themes and computes 5-dimension quality scores.
 *
 * Scores (0–100 each):
 *   design_score       — visual richness, block diversity, color system
 *   commerce_score     — e-commerce readiness (shop/payment/cart blocks)
 *   performance_score  — page count, image handling, responsive blocks
 *   completeness_score — required pages, header, footer, demo data
 *   marketplace_score  — description quality, screenshots, pricing config
 */
class ThemeMarketplaceQualityService
{
    // Minimum requirements for a PASS verdict
    private const MIN_PASS_SCORE = 60;

    // Blocks that count toward design richness
    private const DESIGN_BLOCKS = [
        'hero_split', 'hero_gradient', 'features_grid', 'stats_row',
        'testimonials_slider', 'bento_grid', 'pricing_cards', 'skills_bars',
        'team_cards', 'client_logos', 'process_steps', 'services_cards',
        'trust_badges', 'newsletter_form', 'contact_split',
    ];

    // Blocks that count toward commerce readiness
    private const COMMERCE_BLOCKS = [
        'module:shop_product_grid', 'module:shop_categories',
        'module:shop_cart_button', 'module:shop_countdown',
        'module:shop_search_box', 'pricing_cards',
        'module:pricing_table', 'trust_badges',
    ];

    // Required pages for a complete theme
    private const REQUIRED_PAGES = ['home'];

    // Pages that earn extra completeness points
    private const BONUS_PAGES = ['about', 'contact', 'services', 'blog', 'shop', 'portfolio'];

    public function score(Theme $theme): array
    {
        $issues = [];

        $design       = $this->scoreDesign($theme, $issues);
        $commerce     = $this->scoreCommerce($theme, $issues);
        $performance  = $this->scorePerformance($theme, $issues);
        $completeness = $this->scoreCompleteness($theme, $issues);
        $marketplace  = $this->scoreMarketplace($theme, $issues);

        $overall = (int) round(($design + $commerce + $performance + $completeness + $marketplace) / 5);

        return [
            'design_score'       => $design,
            'commerce_score'     => $commerce,
            'performance_score'  => $performance,
            'completeness_score' => $completeness,
            'marketplace_score'  => $marketplace,
            'overall_score'      => $overall,
            'quality_issues'     => $issues,
            'verdict'            => $overall >= self::MIN_PASS_SCORE ? 'PASS' : 'FAIL',
        ];
    }

    public function scoreAndPersist(Theme $theme): array
    {
        $result = $this->score($theme);

        ThemeMarketplace::updateOrCreate(
            ['theme_id' => $theme->id],
            [
                'design_score'       => $result['design_score'],
                'commerce_score'     => $result['commerce_score'],
                'performance_score'  => $result['performance_score'],
                'completeness_score' => $result['completeness_score'],
                'marketplace_score'  => $result['marketplace_score'],
                'quality_issues'     => $result['quality_issues'],
                'last_scored_at'     => now(),
            ]
        );

        return $result;
    }

    // ─── DESIGN SCORE (0–100) ───────────────────────────────────────────────

    private function scoreDesign(Theme $theme, array &$issues): int
    {
        $score = 0;
        $allBlocks = $this->getAllBlocks($theme);
        $blockTypes = array_unique(array_column($allBlocks, 'type'));

        // Color system defined
        $gs = $theme->global_styles ?? [];
        if (!empty($gs['primary_color'])) {
            $score += 15;
        } else {
            $issues[] = ['severity' => 'MEDIUM', 'code' => 'DESIGN-001', 'message' => 'رنگ اصلی (primary_color) تعریف نشده است'];
        }

        if (!empty($gs['font_family'])) {
            $score += 10;
        } else {
            $issues[] = ['severity' => 'LOW', 'code' => 'DESIGN-002', 'message' => 'فونت (font_family) تعریف نشده است'];
        }

        // Design block diversity
        $designBlocksUsed = array_intersect($blockTypes, self::DESIGN_BLOCKS);
        $score += min(40, count($designBlocksUsed) * 4);

        if (count($designBlocksUsed) < 3) {
            $issues[] = ['severity' => 'HIGH', 'code' => 'DESIGN-003', 'message' => 'تنوع بلوک‌های طراحی کم است (' . count($designBlocksUsed) . ' نوع). حداقل ۳ نوع بلوک طراحی لازم است'];
        }

        // Total block count
        $blockCount = count($allBlocks);
        $score += min(20, (int)($blockCount / 2));

        if ($blockCount < 5) {
            $issues[] = ['severity' => 'HIGH', 'code' => 'DESIGN-004', 'message' => "تعداد بلوک‌ها کم است ({$blockCount}). حداقل ۵ بلوک توصیه می‌شود"];
        }

        // Header and footer defined
        if (!empty($theme->header_blocks)) {
            $score += 8;
        } else {
            $issues[] = ['severity' => 'HIGH', 'code' => 'DESIGN-005', 'message' => 'هدر تعریف نشده است'];
        }

        if (!empty($theme->footer_blocks)) {
            $score += 7;
        } else {
            $issues[] = ['severity' => 'MEDIUM', 'code' => 'DESIGN-006', 'message' => 'فوتر تعریف نشده است'];
        }

        return min(100, $score);
    }

    // ─── COMMERCE SCORE (0–100) ─────────────────────────────────────────────

    private function scoreCommerce(Theme $theme, array &$issues): int
    {
        $allBlocks = $this->getAllBlocks($theme);
        $blockTypes = array_unique(array_column($allBlocks, 'type'));

        $commerceBlocksUsed = array_intersect($blockTypes, self::COMMERCE_BLOCKS);
        $score = min(60, count($commerceBlocksUsed) * 10);

        // Has pricing block
        if (in_array('pricing_cards', $blockTypes) || in_array('module:pricing_table', $blockTypes)) {
            $score += 20;
        }

        // Has shop product grid
        if (in_array('module:shop_product_grid', $blockTypes)) {
            $score += 15;
        }

        // Trust badges
        if (in_array('trust_badges', $blockTypes)) {
            $score += 5;
        }

        // Category is store or marketplace
        if (in_array($theme->category, ['store', 'marketplace', 'saas'])) {
            if (empty($commerceBlocksUsed)) {
                $issues[] = ['severity' => 'HIGH', 'code' => 'COMMERCE-001', 'message' => 'قالب تجاری بدون بلوک‌های تجاری'];
            }
        }

        return min(100, $score);
    }

    // ─── PERFORMANCE SCORE (0–100) ──────────────────────────────────────────

    private function scorePerformance(Theme $theme, array &$issues): int
    {
        $score = 50; // base score
        $pages = $theme->getPageList();
        $allBlocks = $this->getAllBlocks($theme);

        // Reasonable page count (2–10)
        $pageCount = count($pages);
        if ($pageCount >= 2 && $pageCount <= 10) {
            $score += 20;
        } elseif ($pageCount > 10) {
            $score += 10;
            $issues[] = ['severity' => 'LOW', 'code' => 'PERF-001', 'message' => "تعداد صفحات زیاد است ({$pageCount}). بیش از ۱۰ صفحه ممکن است عملکرد را کاهش دهد"];
        }

        // No broken/unknown block types
        $unknownBlocks = [];
        foreach ($allBlocks as $block) {
            $type = $block['type'] ?? '';
            if (empty($type)) {
                $unknownBlocks[] = $block['id'] ?? 'unknown';
            }
        }

        if (empty($unknownBlocks)) {
            $score += 20;
        } else {
            $issues[] = ['severity' => 'MEDIUM', 'code' => 'PERF-002', 'message' => 'بلوک‌هایی با type خالی یافت شد: ' . implode(', ', $unknownBlocks)];
            $score -= 10;
        }

        // Check for duplicate block IDs
        $ids = array_column($allBlocks, 'id');
        $duplicates = array_diff_assoc($ids, array_unique($ids));
        if (empty($duplicates)) {
            $score += 10;
        } else {
            $issues[] = ['severity' => 'HIGH', 'code' => 'PERF-003', 'message' => 'شناسه‌های تکراری بلوک یافت شد: ' . implode(', ', array_unique($duplicates))];
            $score -= 15;
        }

        return max(0, min(100, $score));
    }

    // ─── COMPLETENESS SCORE (0–100) ─────────────────────────────────────────

    private function scoreCompleteness(Theme $theme, array &$issues): int
    {
        $score = 0;
        $pages = $theme->getPageList();
        $pageSlugs = array_keys($pages);

        // Required pages
        foreach (self::REQUIRED_PAGES as $required) {
            if (in_array($required, $pageSlugs)) {
                $score += 20;
            } else {
                $issues[] = ['severity' => 'CRITICAL', 'code' => 'COMP-001', 'message' => "صفحه اجباری '{$required}' وجود ندارد"];
            }
        }

        // Bonus pages
        $bonusCount = count(array_intersect($pageSlugs, self::BONUS_PAGES));
        $score += min(30, $bonusCount * 6);

        if ($bonusCount < 2) {
            $issues[] = ['severity' => 'MEDIUM', 'code' => 'COMP-002', 'message' => 'تعداد صفحات ناکافی است. حداقل ۲ صفحه اضافی توصیه می‌شود'];
        }

        // Home page has blocks
        $homeBlocks = ($pages['home']['blocks'] ?? []);
        if (!empty($homeBlocks)) {
            $score += 20;
        } else {
            $issues[] = ['severity' => 'CRITICAL', 'code' => 'COMP-003', 'message' => 'صفحه اصلی بلوکی ندارد'];
        }

        // Header exists
        if (!empty($theme->header_blocks)) {
            $score += 15;
        } else {
            $issues[] = ['severity' => 'HIGH', 'code' => 'COMP-004', 'message' => 'هدر تعریف نشده است'];
        }

        // Global styles
        if (!empty($theme->global_styles)) {
            $score += 15;
        } else {
            $issues[] = ['severity' => 'MEDIUM', 'code' => 'COMP-005', 'message' => 'استایل‌های جهانی تعریف نشده‌اند'];
        }

        return min(100, $score);
    }

    // ─── MARKETPLACE SCORE (0–100) ──────────────────────────────────────────

    private function scoreMarketplace(Theme $theme, array &$issues): int
    {
        $score = 0;

        // Has description
        $desc = trim($theme->description ?? '');
        if (mb_strlen($desc) >= 50) {
            $score += 25;
        } elseif (mb_strlen($desc) >= 20) {
            $score += 10;
            $issues[] = ['severity' => 'LOW', 'code' => 'MKT-001', 'message' => 'توضیحات قالب کوتاه است (کمتر از ۵۰ کاراکتر)'];
        } else {
            $issues[] = ['severity' => 'MEDIUM', 'code' => 'MKT-002', 'message' => 'توضیحات قالب ندارد یا خیلی کوتاه است'];
        }

        // Has marketplace meta
        $meta = $theme->marketplaceMeta;
        if ($meta) {
            $score += 15;

            // Has marketplace description
            if (!empty($meta->marketplace_description) && mb_strlen($meta->marketplace_description) >= 100) {
                $score += 20;
            }

            // Has screenshots
            if (!empty($meta->screenshots) && count($meta->screenshots) >= 2) {
                $score += 20;
            } else {
                $issues[] = ['severity' => 'LOW', 'code' => 'MKT-003', 'message' => 'اسکرین‌شات مارکت‌پلیس ندارد (حداقل ۲ تصویر توصیه می‌شود)'];
            }
        } else {
            $issues[] = ['severity' => 'HIGH', 'code' => 'MKT-004', 'message' => 'رکورد مارکت‌پلیس برای این قالب وجود ندارد'];
        }

        // Category defined
        if (!empty($theme->category)) {
            $score += 10;
        } else {
            $issues[] = ['severity' => 'MEDIUM', 'code' => 'MKT-005', 'message' => 'دسته‌بندی قالب تعریف نشده است'];
        }

        // Has settings (version, supported_modules)
        $settings = $theme->settings ?? [];
        if (!empty($settings['version'])) {
            $score += 5;
        }
        if (!empty($settings['supported_modules'])) {
            $score += 5;
        }

        return min(100, $score);
    }

    // ─── HELPERS ────────────────────────────────────────────────────────────

    private function getAllBlocks(Theme $theme): array
    {
        $all = [];

        foreach ($theme->header_blocks ?? [] as $b) {
            $all[] = $b;
        }
        foreach ($theme->footer_blocks ?? [] as $b) {
            $all[] = $b;
        }
        foreach ($theme->getPageList() as $page) {
            foreach ($page['blocks'] ?? [] as $b) {
                $all[] = $b;
            }
        }

        return $all;
    }

    // ─── VALIDATION REPORT ──────────────────────────────────────────────────

    public function validate(Theme $theme): array
    {
        $result = $this->score($theme);

        $criticalIssues = array_filter($result['quality_issues'], fn($i) => $i['severity'] === 'CRITICAL');
        $highIssues     = array_filter($result['quality_issues'], fn($i) => $i['severity'] === 'HIGH');
        $mediumIssues   = array_filter($result['quality_issues'], fn($i) => $i['severity'] === 'MEDIUM');
        $lowIssues      = array_filter($result['quality_issues'], fn($i) => $i['severity'] === 'LOW');

        return [
            ...$result,
            'summary' => [
                'critical' => count($criticalIssues),
                'high'     => count($highIssues),
                'medium'   => count($mediumIssues),
                'low'      => count($lowIssues),
                'total'    => count($result['quality_issues']),
            ],
        ];
    }
}
