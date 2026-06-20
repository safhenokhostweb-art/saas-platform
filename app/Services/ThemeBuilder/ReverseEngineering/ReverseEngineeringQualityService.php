<?php

namespace App\Services\ThemeBuilder\ReverseEngineering;

use App\Models\ThemePack;

/**
 * Phase 7 — Reverse Engineering Quality Validation
 *
 * Validates the reconstructed ThemePack against quality criteria:
 *   - Missing sections check
 *   - Broken hierarchy check
 *   - Duplicate IDs check
 *   - Invalid template types check
 *   - Low similarity scores check
 *   - Content completeness check
 *   - Visual profile completeness check
 *
 * Returns a structured quality report with PASS / FAIL / PARTIAL verdict.
 */
class ReverseEngineeringQualityService
{
    // Minimum acceptable scores
    private const MIN_OVERALL_SCORE    = 40;
    private const MIN_STRUCTURE_SCORE  = 30;
    private const MIN_SECTIONS         = 3;
    private const MAX_DUPLICATE_BLOCKS = 0;

    // Required sections for a complete theme
    private const REQUIRED_SECTIONS = ['header', 'hero', 'footer'];

    // Valid block types in this platform
    private const VALID_BLOCK_TYPES = [
        'hero', 'heading', 'text', 'image', 'button', 'divider', 'spacer',
        'card', 'card_grid', 'feature_list', 'icon_box', 'testimonial',
        'faq', 'stats', 'stats_row', 'team', 'cta', 'banner', 'logo_bar',
        'newsletter', 'contact_form', 'nav_header', 'nav_footer', 'nav_logo',
        'nav_links', 'pricing_cards', 'features_grid', 'hero_split',
        'hero_gradient', 'testimonials_slider', 'bento_grid', 'services_cards',
        'contact_split',
    ];

    /**
     * Run full quality validation on the completed job.
     *
     * @param array  $domStructure
     * @param array  $layoutMap
     * @param array  $visualProfile
     * @param array  $commerceProfile
     * @param array  $similarityScores
     * @param ThemePack|null $themePack
     * @return array QualityReport
     */
    public function validate(
        array $domStructure,
        array $layoutMap,
        array $visualProfile,
        array $commerceProfile,
        array $similarityScores,
        ?ThemePack $themePack = null
    ): array {
        $findings = [];
        $score    = 100;

        // ── Check 1: Missing required sections ───────────────────────────────
        $detectSections = $this->getSectionTypes($layoutMap);
        foreach (self::REQUIRED_SECTIONS as $required) {
            if (!in_array($required, $detectSections, true)) {
                $findings[] = $this->finding('MISSING_SECTION', 'HIGH', "بخش اجباری «{$required}» یافت نشد");
                $score -= 15;
            }
        }

        // ── Check 2: Minimum section count ───────────────────────────────────
        $sectionCount = count($detectSections);
        if ($sectionCount < self::MIN_SECTIONS) {
            $findings[] = $this->finding('TOO_FEW_SECTIONS', 'MEDIUM',
                "تعداد بخش‌های تشخیص‌داده‌شده ({$sectionCount}) کمتر از حداقل موردنیاز (" . self::MIN_SECTIONS . ") است");
            $score -= 10;
        }

        // ── Check 3: Broken hierarchy (h1 before h2) ─────────────────────────
        $depth = $domStructure['depth_map'] ?? [];
        if (!empty($depth)) {
            $hierarchyCheck = $this->checkHeadingHierarchy($depth);
            if (!$hierarchyCheck['valid']) {
                $findings[] = $this->finding('BROKEN_HIERARCHY', 'LOW',
                    "سلسله‌مراتب عنوان‌بندی (H1→H2→H3) در صفحه منبع نقض شده: {$hierarchyCheck['detail']}");
                $score -= 5;
            }
        }

        // ── Check 4: Duplicate block IDs in ThemePack ────────────────────────
        if ($themePack) {
            $duplicates = $this->findDuplicateBlockIds($themePack);
            if ($duplicates > self::MAX_DUPLICATE_BLOCKS) {
                $findings[] = $this->finding('DUPLICATE_BLOCK_IDS', 'HIGH',
                    "{$duplicates} شناسه بلاک تکراری در پک تولیدشده وجود دارد");
                $score -= 20;
            }
        }

        // ── Check 5: Invalid block types in ThemePack ────────────────────────
        if ($themePack) {
            $invalidBlocks = $this->findInvalidBlockTypes($themePack);
            if (!empty($invalidBlocks)) {
                $types = implode(', ', $invalidBlocks);
                $findings[] = $this->finding('INVALID_BLOCK_TYPES', 'MEDIUM',
                    "نوع بلاک‌های نامعتبر در پک یافت شد: {$types}");
                $score -= 10;
            }
        }

        // ── Check 6: Low similarity score ────────────────────────────────────
        $overallScore = $similarityScores['overall'] ?? 0;
        if ($overallScore < self::MIN_OVERALL_SCORE) {
            $findings[] = $this->finding('LOW_SIMILARITY_SCORE', 'MEDIUM',
                "امتیاز شباهت کلی ({$overallScore}/100) پایین‌تر از حداقل قابل‌قبول (" . self::MIN_OVERALL_SCORE . ") است");
            $score -= 10;
        }

        // ── Check 7: Low structure score ─────────────────────────────────────
        $structureScore = $similarityScores['structure'] ?? 0;
        if ($structureScore < self::MIN_STRUCTURE_SCORE) {
            $findings[] = $this->finding('LOW_STRUCTURE_SCORE', 'LOW',
                "امتیاز ساختاری ({$structureScore}/100) پایین است — ساختار صفحه کافی تشخیص داده نشد");
            $score -= 5;
        }

        // ── Check 8: Visual profile completeness ─────────────────────────────
        $visualCheck = $this->checkVisualProfile($visualProfile);
        foreach ($visualCheck as $issue) {
            $findings[] = $this->finding('INCOMPLETE_VISUAL_PROFILE', 'LOW', $issue);
            $score -= 3;
        }

        // ── Check 9: Commerce mismatch ───────────────────────────────────────
        $isCommerce      = $commerceProfile['is_commerce'] ?? false;
        $commerceScore   = $commerceProfile['commerce_score'] ?? 0;
        if ($isCommerce && $commerceScore < 30) {
            $findings[] = $this->finding('WEAK_COMMERCE_DETECTION', 'MEDIUM',
                "سایت به‌عنوان فروشگاهی شناسایی شد اما الگوهای تجاری کافی ({$commerceScore}/100) تشخیص داده نشد");
            $score -= 8;
        }

        // ── Check 10: ThemePack structure completeness ───────────────────────
        if ($themePack) {
            $packCheck = $this->checkPackCompleteness($themePack);
            foreach ($packCheck as $issue) {
                $findings[] = $this->finding('INCOMPLETE_PACK', 'MEDIUM', $issue);
                $score -= 5;
            }
        }

        $score   = max(0, min(100, $score));
        $verdict = $this->determineVerdict($score, $findings);

        return [
            'verdict'      => $verdict,
            'score'        => $score,
            'findings'     => $findings,
            'finding_count'=> count($findings),
            'critical'     => count(array_filter($findings, fn ($f) => $f['severity'] === 'CRITICAL')),
            'high'         => count(array_filter($findings, fn ($f) => $f['severity'] === 'HIGH')),
            'medium'       => count(array_filter($findings, fn ($f) => $f['severity'] === 'MEDIUM')),
            'low'          => count(array_filter($findings, fn ($f) => $f['severity'] === 'LOW')),
            'summary'      => $this->buildSummary($score, $verdict, $findings, $similarityScores),
            'validated_at' => now()->toISOString(),
        ];
    }

    // ── Private validators ────────────────────────────────────────────────────

    private function getSectionTypes(array $layoutMap): array
    {
        return array_map(fn ($s) => $s['type'], $layoutMap['sections'] ?? []);
    }

    private function checkHeadingHierarchy(array $depthMap): array
    {
        $prev = 0;
        foreach ($depthMap as $heading) {
            $level = (int) ($heading['level'] ?? 1);
            if ($level > $prev + 1 && $prev > 0) {
                return ['valid' => false, 'detail' => "H{$prev} → H{$level} (سطح رد شد)"];
            }
            $prev = $level;
        }
        return ['valid' => true, 'detail' => ''];
    }

    private function findDuplicateBlockIds(ThemePack $pack): int
    {
        $packData = $pack->pack_data ?? [];
        $ids      = [];

        $zones = ['headers', 'footers', 'pages'];
        foreach ($zones as $zone) {
            foreach ($packData[$zone] ?? [] as $entry) {
                $blocks = $entry['blocks'] ?? [];
                foreach ($blocks as $block) {
                    $ids[] = $block['id'] ?? '';
                }
            }
        }

        $ids     = array_filter($ids);
        $unique  = array_unique($ids);
        return count($ids) - count($unique);
    }

    private function findInvalidBlockTypes(ThemePack $pack): array
    {
        $packData = $pack->pack_data ?? [];
        $invalid  = [];

        $zones = ['headers', 'footers', 'pages'];
        foreach ($zones as $zone) {
            foreach ($packData[$zone] ?? [] as $entry) {
                foreach ($entry['blocks'] ?? [] as $block) {
                    $type = $block['type'] ?? '';
                    if ($type && !in_array($type, self::VALID_BLOCK_TYPES, true)) {
                        $invalid[] = $type;
                    }
                }
            }
        }

        return array_unique($invalid);
    }

    private function checkVisualProfile(array $visual): array
    {
        $issues  = [];
        $palette = $visual['color_palette'] ?? [];

        if (empty($palette['primary'])) {
            $issues[] = 'رنگ اصلی تشخیص داده نشد';
        }
        if (empty($visual['typography']['primary_font'])) {
            $issues[] = 'فونت اصلی تشخیص داده نشد';
        }
        if (empty($visual['border_radius']['scale'])) {
            $issues[] = 'سیستم گوشه‌گردانی تشخیص داده نشد';
        }

        return $issues;
    }

    private function checkPackCompleteness(ThemePack $pack): array
    {
        $issues   = [];
        $packData = $pack->pack_data ?? [];

        if (empty($packData['headers'])) {
            $issues[] = 'پک فاقد هدر است';
        }
        if (empty($packData['footers'])) {
            $issues[] = 'پک فاقد فوتر است';
        }
        if (empty($packData['pages'])) {
            $issues[] = 'پک فاقد صفحه است';
        }
        if (empty($packData['global_styles']['primary_color'])) {
            $issues[] = 'پک فاقد رنگ اصلی در global_styles است';
        }

        $pageBlocks = count($packData['pages'][0]['blocks'] ?? []);
        if ($pageBlocks < 2) {
            $issues[] = "صفحه اصلی فقط {$pageBlocks} بلاک دارد (حداقل ۲ بلاک لازم است)";
        }

        return $issues;
    }

    private function determineVerdict(int $score, array $findings): string
    {
        $hasCritical = count(array_filter($findings, fn ($f) => $f['severity'] === 'CRITICAL')) > 0;
        $highCount   = count(array_filter($findings, fn ($f) => $f['severity'] === 'HIGH'));

        if ($hasCritical || $highCount >= 3) return 'FAIL';
        if ($score >= 75 && $highCount === 0) return 'PASS';
        if ($score >= 50) return 'PARTIAL';
        return 'FAIL';
    }

    private function buildSummary(int $score, string $verdict, array $findings, array $scores): string
    {
        $bestMatch = $scores['best_match_label'] ?? '';
        $bestScore = $scores['best_match_score'] ?? 0;
        $count     = count($findings);

        $verdictLabel = match ($verdict) {
            'PASS'    => 'تایید شد',
            'PARTIAL' => 'نیمه‌تایید شد',
            'FAIL'    => 'رد شد',
            default   => $verdict,
        };

        $summary = "امتیاز کیفی: {$score}/100 | وضعیت: {$verdictLabel}";
        if ($bestMatch) {
            $summary .= " | شباهت با {$bestMatch}: {$bestScore}%";
        }
        if ($count > 0) {
            $summary .= " | {$count} یافته";
        }

        return $summary;
    }

    private function finding(string $code, string $severity, string $message): array
    {
        return [
            'code'     => $code,
            'severity' => $severity,  // CRITICAL / HIGH / MEDIUM / LOW
            'message'  => $message,
        ];
    }
}
