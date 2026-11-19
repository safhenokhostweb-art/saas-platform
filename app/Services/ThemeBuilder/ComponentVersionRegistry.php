<?php

namespace App\Services\ThemeBuilder;

use App\Models\CustomerInstallationPackage;
use App\Models\ThemeRelease;

class ComponentVersionRegistry
{
    public const CANVAS_VERSION = '2.2.0';
    public const WIDGETS_VERSION = '2.5.0';
    public const CUSTOMER_CORE_VERSION = '1.3.0';
    public const MOTION_GRAPHICS_VERSION = '1.2.0';

    /**
     * Get a specific component's metadata.
     *
     * @return array{name: string, slug: string, version: string, type: string, summary: string, changelog: list<string>, updated_at: string}|null
     */
    public static function get(string $component): ?array
    {
        return self::all()[$component] ?? null;
    }

    /**
     * Get authoritative metadata and changelogs for all platform components.
     *
     * @return array<string, array{
     *     name: string,
     *     slug: string,
     *     version: string,
     *     type: string,
     *     summary: string,
     *     changelog: list<string>,
     *     updated_at: string
     * }>
     */
    public static function all(): array
    {
        $activePkg = CustomerInstallationPackage::active();
        $pkgVersion = $activePkg?->version ?? self::CUSTOMER_CORE_VERSION;

        return [
            'theme' => [
                'name'       => 'قالب‌ها و پوسته‌ها (Themes)',
                'slug'       => 'theme',
                'version'    => self::latestThemeVersion(),
                'type'       => 'semver',
                'summary'    => 'سیستم طراحی مستقل، چیدمان صفحات، استایل‌های اختصاصی و ایزولاسیون کامل',
                'changelog'  => [
                    'پشتیبانی از انتشار ۲۴ صفحه‌ای قالب خورشیدی (Solar Industry v1.1.0)',
                    'جداسازی کامل استایل‌های ورود و ثبت‌نام در قالب‌های اختصاصی',
                    'الگوریتم تطبیق ۳-طرفه و حفاظت ۱۰۰٪ از ویرایش‌های کاربران در ارتقای ناوگان',
                ],
                'updated_at' => '2026-11-18',
            ],
            'canvas' => [
                'name'       => 'موتور و بلوک‌های کانواس (Canvas Engine)',
                'slug'       => 'canvas',
                'version'    => self::CANVAS_VERSION,
                'type'       => 'semver',
                'summary'    => 'موتور رندرینگ بلادرنگ، مدیریت اسلات‌ها، ساختار درختی و تفکیک سریع Fast-Path',
                'changelog'  => [
                    'بهینه‌سازی رندر بلادرنگ ویجت‌ها بدون بارگذاری مجدد صفحه',
                    'پشتیبانی از سپرده‌گذاری خودکار بلوک‌های جدید در کتابخانه الگوهای مشتری (SiteThemeTemplate)',
                    'تعریف صریح اسلات‌های هدر و فوتر و مدیریت منعطف استایل‌های سراسری',
                ],
                'updated_at' => '2026-11-18',
            ],
            'widgets' => [
                'name'       => 'کتابخانه ویجت‌ها (Widgets Library)',
                'slug'       => 'widgets',
                'version'    => self::WIDGETS_VERSION,
                'type'       => 'semver',
                'summary'    => 'مجموعه ماژولار ویجت‌های تجاری، بنتو گرید، اسلایدرها و ابزارهای تعاملی',
                'changelog'  => [
                    'ویجت حرفه‌ای هدر مدرن RTL با منوی ریسپانسیو اختصاصی',
                    'اسلایدر چندمنظوره کاروسل با پشتیبانی کامل از تاچ و شتاب سخت‌افزاری',
                    'ویجت فرم محاسبه‌گر نیروگاه خورشیدی با قابلیت فرمول‌بندی داینامیک',
                ],
                'updated_at' => '2026-11-18',
            ],
            'customer_core' => [
                'name'       => 'هسته و داشبورد مشتری (Customer Site Core & Dashboard)',
                'slug'       => 'customer_core',
                'version'    => $pkgVersion,
                'type'       => 'semver',
                'summary'    => 'معماری چندمستأجری، لایسنسینگ امن، پنل کاربری مستقل و احراز هویت ماژولار',
                'changelog'  => [
                    'پشتیبانی از گارد نسخه حداقلی (MINIMUM_SUPPORTED_VERSION 1.1.0)',
                    'اندپوینت امن سرور-به-سرور ارتقا و بازگشت به نسخه قبل (/builder/api/theme/rollback)',
                    'ایزولاسیون کامل کوکی‌ها و توکن‌های ورود بین پلتفرم و سایت‌های مشتریان',
                ],
                'updated_at' => '2026-11-18',
            ],
            'motion_graphics' => [
                'name'       => 'موشن‌گرافیک‌ها و وکتورهای متحرک (Motion Graphics)',
                'slug'       => 'motion_graphics',
                'version'    => self::MOTION_GRAPHICS_VERSION,
                'type'       => 'semver',
                'summary'    => 'انیمیشن‌های تعاملی SVG و Lottie، بهینه‌شده برای نرخ فریم بالا و بارگذاری سریع',
                'changelog'  => [
                    'پکیج موشن‌گرافیک‌های صنعتی خورشیدی نسخه ۱.۰.۰ با قابلیت تغییر رنگ',
                    'سیستم اعطای خودکار لایسنس موشن‌ها در هنگام راه‌اندازی سایت مشتری',
                    'سازگاری کامل با استانداردهای ریسپانسیو و کاهش مصرف انرژی در موبایل',
                ],
                'updated_at' => '2026-11-18',
            ],
        ];
    }

    /**
     * Get the latest published theme release version across active catalog.
     */
    public static function latestThemeVersion(): string
    {
        $latest = ThemeRelease::where('status', ThemeRelease::STATUS_PUBLISHED)
            ->latest('id')
            ->first();

        return $latest?->version ?? '1.1.0';
    }

    /**
     * Returns a snapshot of current versions.
     *
     * @return array<string, string>
     */
    public static function currentVersionsSnapshot(?string $themeVersion = null): array
    {
        $activePkg = CustomerInstallationPackage::active();

        return [
            'theme'           => $themeVersion ?? self::latestThemeVersion(),
            'canvas'          => self::CANVAS_VERSION,
            'widgets'         => self::WIDGETS_VERSION,
            'customer_core'   => $activePkg?->version ?? self::CUSTOMER_CORE_VERSION,
            'motion_graphics' => self::MOTION_GRAPHICS_VERSION,
        ];
    }

    /**
     * Generates a human-readable diff / changelog summary string.
     */
    public static function formatChangelogSummary(string $component, array $fromVersions, array $toVersions): string
    {
        $all = self::all();
        $meta = $all[$component] ?? null;
        $name = $meta['name'] ?? $component;

        $from = $fromVersions[$component] ?? '1.0.0';
        $to = $toVersions[$component] ?? ($meta['version'] ?? '1.1.0');

        $lines = ["ارتقای {$name} از نسخه {$from} به {$to}:"];
        if (!empty($meta['changelog'])) {
            foreach ($meta['changelog'] as $logItem) {
                $lines[] = "• " . $logItem;
            }
        }

        return implode("\n", $lines);
    }
}
