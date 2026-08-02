<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Services;

use SaaSPlatform\Modules\SeoAdvanced\Models\SeoAdvancedAudit;

/**
 * Phase 18-E — Part 6: Performance/Technical/Mobile checks — distinct
 * from SeoAuditor (page-content checks: title/description/heading/
 * canonical/OG/URL structure, unchanged). Deliberately never fetches a
 * URL itself — every signal (HTML size, image list, link statuses,
 * viewport meta presence) is supplied by the caller, so this service
 * stays fully deterministic and testable without a real HTTP client or
 * external dependency, matching this module's established convention
 * (see SeoAuditor's own docblock: "deliberately simple, deterministic
 * rules rather than any external service call").
 */
class TechnicalSeoScannerService
{
    private const MAX_PAGE_SIZE_BYTES = 2 * 1024 * 1024; // 2 MB
    private const MAX_IMAGE_SIZE_BYTES = 300 * 1024; // 300 KB

    /**
     * @param  array{
     *     html_size_bytes?: int,
     *     images?: array<int, array{src: string, size_bytes?: int, format?: string}>,
     *     links?: array<int, array{url: string, status: ?int}>,
     *     has_viewport_meta?: bool,
     * }  $signals
     * @return array{issues: array, score_penalty: int}
     */
    public function scan(int $siteId, string $pageUrl, ?string $title, ?string $description, array $signals = []): array
    {
        $issues = [];

        $issues = array_merge($issues, $this->scanPerformance($signals));
        $issues = array_merge($issues, $this->scanTechnical($siteId, $pageUrl, $title, $description, $signals));
        $issues = array_merge($issues, $this->scanMobile($signals));

        $penalty = array_sum(array_map(fn ($i) => match ($i['severity']) {
            SeoAuditor::SEVERITY_CRITICAL => 20,
            SeoAuditor::SEVERITY_WARNING => 10,
            default => 5,
        }, $issues));

        return ['issues' => $issues, 'score_penalty' => $penalty];
    }

    /** @return array<int, array{code: string, severity: string, message: string, suggested_fix: string}> */
    private function scanPerformance(array $signals): array
    {
        $issues = [];

        $pageSize = $signals['html_size_bytes'] ?? null;
        if ($pageSize !== null && $pageSize > self::MAX_PAGE_SIZE_BYTES) {
            $issues[] = $this->issue(
                'page_size_too_large',
                SeoAuditor::SEVERITY_WARNING,
                'حجم HTML صفحه (' . $this->formatBytes($pageSize) . ') بیش از حد توصیه‌شده است.',
                'HTML غیرضروری (کدهای اضافی، Inline CSS/JS بزرگ) را کاهش دهید.'
            );
        }

        foreach ($signals['images'] ?? [] as $image) {
            $size = $image['size_bytes'] ?? null;
            if ($size !== null && $size > self::MAX_IMAGE_SIZE_BYTES) {
                $issues[] = $this->issue(
                    'image_too_large',
                    SeoAuditor::SEVERITY_RECOMMENDATION,
                    'تصویر «' . ($image['src'] ?? '') . '» حجم زیادی دارد (' . $this->formatBytes($size) . ').',
                    'تصویر را فشرده‌سازی کنید یا به فرمت WebP تبدیل کنید.'
                );
            }
        }

        return $issues;
    }

    /** @return array<int, array{code: string, severity: string, message: string, suggested_fix: string}> */
    private function scanTechnical(int $siteId, string $pageUrl, ?string $title, ?string $description, array $signals): array
    {
        $issues = [];

        foreach ($signals['links'] ?? [] as $link) {
            $status = $link['status'] ?? null;
            if ($status !== null && $status >= 400) {
                $issues[] = $this->issue(
                    'broken_link',
                    SeoAuditor::SEVERITY_CRITICAL,
                    'لینک شکسته یافت شد: «' . ($link['url'] ?? '') . '» (HTTP ' . $status . ')',
                    'لینک را اصلاح کنید یا حذف کنید.'
                );
            }
        }

        if (!empty($title)) {
            $duplicateTitle = SeoAdvancedAudit::where('site_id', $siteId)
                ->where('page_url', '!=', $pageUrl)
                ->where('title', $title)
                ->exists();

            if ($duplicateTitle) {
                $issues[] = $this->issue(
                    'duplicate_title',
                    SeoAuditor::SEVERITY_WARNING,
                    'این عنوان (title) در صفحه دیگری از همین سایت هم استفاده شده است.',
                    'برای هر صفحه یک عنوان منحصربه‌فرد بنویسید.'
                );
            }
        }

        if (!empty($description)) {
            $duplicateDescription = SeoAdvancedAudit::where('site_id', $siteId)
                ->where('page_url', '!=', $pageUrl)
                ->where('description', $description)
                ->exists();

            if ($duplicateDescription) {
                $issues[] = $this->issue(
                    'duplicate_description',
                    SeoAuditor::SEVERITY_WARNING,
                    'این توضیحات متا (description) در صفحه دیگری از همین سایت هم استفاده شده است.',
                    'برای هر صفحه یک توضیح منحصربه‌فرد بنویسید.'
                );
            }
        }

        return $issues;
    }

    /** @return array<int, array{code: string, severity: string, message: string, suggested_fix: string}> */
    private function scanMobile(array $signals): array
    {
        if (($signals['has_viewport_meta'] ?? null) === false) {
            return [$this->issue(
                'missing_viewport_meta',
                SeoAuditor::SEVERITY_CRITICAL,
                'تگ viewport برای نمایش موبایل یافت نشد.',
                'تگ <meta name="viewport" content="width=device-width, initial-scale=1"> را اضافه کنید.'
            )];
        }

        return [];
    }

    private function issue(string $code, string $severity, string $message, string $suggestedFix): array
    {
        return ['code' => $code, 'severity' => $severity, 'message' => $message, 'suggested_fix' => $suggestedFix];
    }

    private function formatBytes(int $bytes): string
    {
        return $bytes >= 1024 * 1024
            ? round($bytes / (1024 * 1024), 1) . ' MB'
            : round($bytes / 1024, 1) . ' KB';
    }
}
