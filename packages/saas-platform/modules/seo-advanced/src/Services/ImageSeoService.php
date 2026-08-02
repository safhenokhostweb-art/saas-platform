<?php

namespace SaaSPlatform\Modules\SeoAdvanced\Services;

/**
 * Phase 18-E — Part 7: Image SEO — audit + suggestions ONLY. Never
 * touches, converts, compresses, or renames a customer's actual image
 * files ("بدون تغییر فایل‌های مشتری بدون اجازه" — no customer file
 * changes without permission) — every method here is pure analysis over
 * data the caller supplies, returning recommendations for a human (or a
 * future, explicitly-authorized automation) to act on.
 */
class ImageSeoService
{
    private const MAX_RECOMMENDED_BYTES = 300 * 1024; // 300 KB
    private const MODERN_FORMATS = ['webp', 'avif'];
    private const ACCEPTABLE_LEGACY_FORMATS = ['jpg', 'jpeg', 'png', 'svg', 'gif'];

    /**
     * @param  array<int, array{src: string, alt?: ?string, size_bytes?: ?int, format?: ?string}>  $images
     * @return array<int, array{code: string, severity: string, message: string, suggested_fix: string}>
     */
    public function audit(array $images): array
    {
        $issues = [];

        foreach ($images as $image) {
            $src = $image['src'] ?? '';

            if (empty(trim((string) ($image['alt'] ?? '')))) {
                $issues[] = $this->issue('missing_alt', 'critical', "تصویر «{$src}» فاقد متن جایگزین (alt) است.", 'یک متن alt توصیفی و مرتبط با محتوای تصویر اضافه کنید.');
            }

            $size = $image['size_bytes'] ?? null;
            if ($size !== null && $size > self::MAX_RECOMMENDED_BYTES) {
                $issues[] = $this->issue('large_image', 'warning', "تصویر «{$src}» حجم بالایی دارد (" . round($size / 1024) . ' KB).', 'فشرده‌سازی یا تبدیل به WebP می‌تواند حجم را تا ۷۰٪ کاهش دهد.');
            }

            $format = strtolower($image['format'] ?? pathinfo($src, PATHINFO_EXTENSION));
            if ($format !== '' && !in_array($format, [...self::MODERN_FORMATS, ...self::ACCEPTABLE_LEGACY_FORMATS], true)) {
                $issues[] = $this->issue('unsupported_format', 'warning', "فرمت تصویر «{$src}» ({$format}) برای وب مناسب نیست.", 'به فرمت WebP یا JPEG تبدیل کنید.');
            } elseif (in_array($format, self::ACCEPTABLE_LEGACY_FORMATS, true) && $format !== 'svg') {
                $issues[] = $this->issue('not_modern_format', 'recommendation', "تصویر «{$src}» می‌تواند به فرمت مدرن‌تر WebP تبدیل شود.", 'تبدیل به WebP حجم را کاهش داده و سرعت بارگذاری را افزایش می‌دهد.');
            }

            if ($this->hasPoorFilename($src)) {
                $issues[] = $this->issue('poor_filename', 'recommendation', "نام فایل «{$src}» توصیفی نیست.", 'از نام‌های توصیفی و شامل کلمه کلیدی استفاده کنید (مثال: kafsh-varzeshi-mardane.jpg به‌جای IMG_1234.jpg).');
            }
        }

        return $issues;
    }

    private function hasPoorFilename(string $src): bool
    {
        $basename = pathinfo($src, PATHINFO_FILENAME);

        // Generic camera/CMS-generated patterns: IMG_1234, DSC0001, image-1, photo123, screenshot...
        return (bool) preg_match('/^(img|dsc|image|photo|screenshot|untitled)[-_]?\d*$/i', $basename);
    }

    private function issue(string $code, string $severity, string $message, string $suggestedFix): array
    {
        return ['code' => $code, 'severity' => $severity, 'message' => $message, 'suggested_fix' => $suggestedFix];
    }
}
