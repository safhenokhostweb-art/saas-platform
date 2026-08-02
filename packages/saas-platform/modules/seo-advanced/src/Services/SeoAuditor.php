<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Services;

use SaaSPlatform\Modules\SeoAdvanced\Contracts\SeoAuditorInterface;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedAudit;
use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedSetting;

/**
 * Phase 18 — Part 4 / Phase 18-C — Part 4 / Phase 18-D — Part 2 & 3:
 * title length / description length / missing meta / missing image alt
 * checks (page-level), heading/canonical presence, the new heading-
 * hierarchy / OpenGraph / URL-structure checks (all opt-in — a caller
 * that doesn't pass the new optional arguments gets exactly the Phase
 * 18-C behavior, byte-for-byte), and site-level Technical checks
 * (robots/schema/sitemap). Title/description thresholds fall back to
 * the class constants when a site has no configured override (see
 * SeoAdvancedSetting::title_min_length etc, Phase 18-D — Part 2).
 */
class SeoAuditor implements SeoAuditorInterface
{
    private const TITLE_MIN = 30;
    private const TITLE_MAX = 60;
    private const DESCRIPTION_MIN = 70;
    private const DESCRIPTION_MAX = 160;
    private const TECHNICAL_PENALTY = 10;
    private const URL_MAX_LENGTH = 75;

    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_RECOMMENDATION = 'recommendation';

    private const SEVERITY_PENALTY = [
        self::SEVERITY_CRITICAL => 20,
        self::SEVERITY_WARNING => 10,
        self::SEVERITY_RECOMMENDATION => 5,
    ];

    public function audit(
        ?string $title,
        ?string $description,
        array $imageAltTexts = [],
        ?string $heading = null,
        ?string $canonical = null,
        ?int $titleMin = null,
        ?int $titleMax = null,
        ?int $descriptionMin = null,
        ?int $descriptionMax = null,
        ?array $headings = null,
        ?array $og = null,
        ?string $urlPath = null,
    ): array {
        $titleMin ??= self::TITLE_MIN;
        $titleMax ??= self::TITLE_MAX;
        $descriptionMin ??= self::DESCRIPTION_MIN;
        $descriptionMax ??= self::DESCRIPTION_MAX;

        $titleLength = $title !== null ? mb_strlen($title) : 0;
        $descriptionLength = $description !== null ? mb_strlen($description) : 0;

        $missingMeta = [];
        $issues = [];

        if (empty($title)) {
            $missingMeta[] = 'title';
            $issues[] = $this->issue('title_missing', self::SEVERITY_CRITICAL, 'عنوان صفحه (title) تنظیم نشده است.', 'یک عنوان منحصربه‌فرد و توصیفی برای این صفحه بنویسید.');
        } elseif ($titleLength < $titleMin || $titleLength > $titleMax) {
            $missingMeta[] = 'title_length_out_of_range';
            $issues[] = $this->issue('title_length_out_of_range', self::SEVERITY_WARNING, "طول عنوان ({$titleLength} کاراکتر) خارج از بازه پیشنهادی ({$titleMin} تا {$titleMax}) است.", "طول عنوان را بین {$titleMin} تا {$titleMax} کاراکتر تنظیم کنید.");
        }

        if (empty($description)) {
            $missingMeta[] = 'description';
            $issues[] = $this->issue('description_missing', self::SEVERITY_CRITICAL, 'توضیحات متا (meta description) تنظیم نشده است.', 'یک توضیح جذاب و مرتبط با محتوای صفحه بنویسید.');
        } elseif ($descriptionLength < $descriptionMin || $descriptionLength > $descriptionMax) {
            $missingMeta[] = 'description_length_out_of_range';
            $issues[] = $this->issue('description_length_out_of_range', self::SEVERITY_WARNING, "طول توضیحات ({$descriptionLength} کاراکتر) خارج از بازه پیشنهادی ({$descriptionMin} تا {$descriptionMax}) است.", "طول توضیحات را بین {$descriptionMin} تا {$descriptionMax} کاراکتر تنظیم کنید.");
        }

        if ($heading !== null && trim($heading) === '') {
            $missingMeta[] = 'heading';
            $issues[] = $this->issue('h1_missing', self::SEVERITY_CRITICAL, 'تیتر اصلی (H1) صفحه یافت نشد.', 'یک تگ H1 منحصربه‌فرد شامل کلمه کلیدی اصلی صفحه اضافه کنید.');
        }

        if ($canonical !== null && trim($canonical) === '') {
            $missingMeta[] = 'canonical';
            $issues[] = $this->issue('canonical_missing', self::SEVERITY_WARNING, 'آدرس Canonical برای این صفحه تنظیم نشده است.', 'یک canonical URL برای جلوگیری از محتوای تکراری تنظیم کنید.');
        }

        $missingAltImages = array_values(array_filter(
            $imageAltTexts,
            fn ($alt) => $alt === null || trim((string) $alt) === ''
        ));
        foreach ($missingAltImages as $ignored) {
            $issues[] = $this->issue('image_alt_missing', self::SEVERITY_RECOMMENDATION, 'یک تصویر فاقد متن جایگزین (alt) است.', 'برای هر تصویر یک متن alt توصیفی اضافه کنید.');
        }

        $score = 100;
        $score -= count($missingMeta) * 20;
        $score -= count($missingAltImages) * 5;

        if ($headings !== null) {
            [$headingIssues, $headingPenalty] = $this->auditHeadingHierarchy($headings);
            $issues = array_merge($issues, $headingIssues);
            $score -= $headingPenalty;
        }

        if ($og !== null) {
            [$ogIssues, $ogPenalty] = $this->auditOpenGraph($og);
            $issues = array_merge($issues, $ogIssues);
            $score -= $ogPenalty;
        }

        if ($urlPath !== null) {
            [$urlIssues, $urlPenalty] = $this->auditUrlStructure($urlPath);
            $issues = array_merge($issues, $urlIssues);
            $score -= $urlPenalty;
        }

        $score = max(0, min(100, $score));

        return [
            'title_length' => $titleLength,
            'description_length' => $descriptionLength,
            'missing_meta' => $missingMeta,
            'missing_alt_images' => array_values($missingAltImages),
            'issues' => $issues,
            'score' => $score,
        ];
    }

    /**
     * @param  array<int, array{level: int, text?: string}>  $headings  full ordered heading list of the page (H1..H6)
     * @return array{0: array, 1: int} [issues, penalty]
     */
    private function auditHeadingHierarchy(array $headings): array
    {
        $issues = [];
        $penalty = 0;

        $h1Count = count(array_filter($headings, fn ($h) => (int) ($h['level'] ?? 0) === 1));

        if ($h1Count === 0) {
            $issues[] = $this->issue('headings_no_h1', self::SEVERITY_CRITICAL, 'هیچ تگ H1 در ساختار تیترهای صفحه یافت نشد.', 'دقیقاً یک تگ H1 شامل موضوع اصلی صفحه اضافه کنید.');
            $penalty += self::SEVERITY_PENALTY[self::SEVERITY_CRITICAL];
        } elseif ($h1Count > 1) {
            $issues[] = $this->issue('headings_multiple_h1', self::SEVERITY_WARNING, "صفحه {$h1Count} تگ H1 دارد.", 'فقط یک تگ H1 در هر صفحه استفاده کنید.');
            $penalty += self::SEVERITY_PENALTY[self::SEVERITY_WARNING];
        }

        $previousLevel = null;
        foreach ($headings as $heading) {
            $level = (int) ($heading['level'] ?? 0);
            if ($previousLevel !== null && $level - $previousLevel > 1) {
                $issues[] = $this->issue('headings_level_skip', self::SEVERITY_RECOMMENDATION, "پرش در سلسله‌مراتب تیترها: از H{$previousLevel} مستقیم به H{$level} رفته است.", 'از تیترهای میانی استفاده کنید تا سطح‌ها پرش نداشته باشند.');
                $penalty += self::SEVERITY_PENALTY[self::SEVERITY_RECOMMENDATION];
            }
            $previousLevel = $level;
        }

        return [$issues, $penalty];
    }

    /**
     * @param  array{title?: ?string, description?: ?string, image?: ?string}  $og
     * @return array{0: array, 1: int} [issues, penalty]
     */
    private function auditOpenGraph(array $og): array
    {
        $missing = [];
        foreach (['title', 'description', 'image'] as $field) {
            if (empty($og[$field] ?? null)) {
                $missing[] = $field;
            }
        }

        if (empty($missing)) {
            return [[], 0];
        }

        $issue = $this->issue(
            'og_incomplete',
            self::SEVERITY_WARNING,
            'برچسب‌های OpenGraph ('.implode('، ', $missing).') تنظیم نشده‌اند.',
            'برچسب‌های og:title، og:description و og:image را برای نمایش بهتر در شبکه‌های اجتماعی تنظیم کنید.'
        );

        return [[$issue], self::SEVERITY_PENALTY[self::SEVERITY_WARNING]];
    }

    /**
     * @return array{0: array, 1: int} [issues, penalty]
     */
    private function auditUrlStructure(string $urlPath): array
    {
        $issues = [];
        $penalty = 0;

        if ($urlPath !== mb_strtolower($urlPath)) {
            $issues[] = $this->issue('url_not_lowercase', self::SEVERITY_RECOMMENDATION, 'آدرس صفحه شامل حروف بزرگ است.', 'از حروف کوچک در ساختار URL استفاده کنید.');
            $penalty += self::SEVERITY_PENALTY[self::SEVERITY_RECOMMENDATION];
        }

        if (str_contains($urlPath, '_') || str_contains($urlPath, ' ')) {
            $issues[] = $this->issue('url_bad_separators', self::SEVERITY_RECOMMENDATION, 'آدرس صفحه شامل زیرخط (_) یا فاصله است.', 'به‌جای زیرخط یا فاصله از خط تیره (-) استفاده کنید.');
            $penalty += self::SEVERITY_PENALTY[self::SEVERITY_RECOMMENDATION];
        }

        if (mb_strlen($urlPath) > self::URL_MAX_LENGTH) {
            $issues[] = $this->issue('url_too_long', self::SEVERITY_RECOMMENDATION, 'آدرس صفحه طولانی است ('.mb_strlen($urlPath).' کاراکتر).', 'آدرس صفحه را کوتاه‌تر و خواناتر کنید.');
            $penalty += self::SEVERITY_PENALTY[self::SEVERITY_RECOMMENDATION];
        }

        return [$issues, $penalty];
    }

    private function issue(string $code, string $severity, string $message, string $suggestedFix): array
    {
        return ['code' => $code, 'severity' => $severity, 'message' => $message, 'suggested_fix' => $suggestedFix];
    }

    public function technicalChecks(int $siteId): array
    {
        $settings = SeoAdvancedSetting::where('site_id', $siteId)->first();

        return [
            'robots_configured' => $settings !== null && !empty($settings->robots_directive),
            'schema_configured' => $settings !== null && !empty($settings->schema_type),
            // The sitemap route is always registered once this module is
            // installed at all (see routes/web.php) — it may return zero
            // URLs, but the capability itself is never "missing".
            'sitemap_available' => true,
        ];
    }

    private function technicalIssueText(string $check): array
    {
        return match ($check) {
            'robots_configured' => ['دستورالعمل robots برای این سایت تنظیم نشده است.', 'دستورالعمل robots را از تنظیمات SEO پیشرفته مشخص کنید.'],
            'schema_configured' => ['داده ساختاریافته (Schema.org) برای این سایت تنظیم نشده است.', 'نوع Schema (Organization/LocalBusiness/WebSite) را در تنظیمات انتخاب کنید.'],
            'sitemap_available' => ['نقشه سایت (sitemap) در دسترس نیست.', 'اطمینان حاصل کنید ماژول به‌درستی نصب شده است.'],
            default => ["بررسی فنی {$check} ناموفق بود.", 'این مورد را در تنظیمات SEO بررسی کنید.'],
        };
    }

    public function auditAndStore(
        int $siteId,
        string $pageUrl,
        ?string $title,
        ?string $description,
        array $imageAltTexts = [],
        ?string $heading = null,
        ?string $canonical = null,
        ?array $headings = null,
        ?array $og = null,
        ?string $urlPath = null,
    ): SeoAdvancedAudit {
        $settings = SeoAdvancedSetting::where('site_id', $siteId)->first();

        $result = $this->audit(
            $title,
            $description,
            $imageAltTexts,
            $heading,
            $canonical,
            $settings?->title_min_length,
            $settings?->title_max_length,
            $settings?->description_min_length,
            $settings?->description_max_length,
            $headings,
            $og,
            $urlPath,
        );

        $technical = $this->technicalChecks($siteId);
        $missingTechnical = [];
        $issues = $result['issues'];
        foreach ($technical as $check => $passed) {
            if (!$passed) {
                $missingTechnical[] = "technical:{$check}";
                [$message, $suggestedFix] = $this->technicalIssueText($check);
                $issues[] = $this->issue("technical_{$check}", self::SEVERITY_WARNING, $message, $suggestedFix);
            }
        }

        $missingMeta = array_merge($result['missing_meta'], $missingTechnical);
        $score = max(0, $result['score'] - (count($missingTechnical) * self::TECHNICAL_PENALTY));

        return SeoAdvancedAudit::create([
            'site_id' => $siteId,
            'page_url' => $pageUrl,
            'title' => $title,
            'description' => $description,
            'title_length' => $result['title_length'],
            'description_length' => $result['description_length'],
            'missing_meta' => $missingMeta,
            'missing_alt_images' => $result['missing_alt_images'],
            'issues' => $issues,
            'score' => $score,
            'audited_at' => now(),
        ]);
    }
}
