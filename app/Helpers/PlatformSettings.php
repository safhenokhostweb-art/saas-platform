<?php
namespace App\Helpers;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PlatformSettings
{
    public const GENERAL_KEYS = [
        'platform_name' => 'پلتفرم SaaS',
        'platform_logo' => '',
        'contact_email' => '',
        'contact_phone' => '',
        'contact_address' => '',
        'registration_open' => '1',
    ];

    public const THEME_KEYS = [
        'theme_primary' => '#4f46e5',
        'theme_secondary' => '#6366f1',
        'theme_menu_bg' => '#ffffff',
        'theme_menu_text' => '#374151',
        'theme_menu_link_active' => '#2563eb',
        'theme_title_color' => '#111827',
        'theme_link_color' => '#4f46e5',
        'theme_link_hover' => '#4338ca',
        /** پالت پیش‌فرض نوار کناری: آبیِ ملایم با کنتراست خوانا (پنل ادمین پلتفرم) */
        'theme_sidebar_bg' => '#5a7fa8',
        'theme_sidebar_bg_bottom' => '#456f94',
        'theme_sidebar_text' => '#f1f6fb',
        'theme_sidebar_muted' => '#d8ebfb',
        'theme_sidebar_accent' => '#7dd3fc',
        'theme_sidebar_active_bg' => '#6b8eb5',
        'theme_sidebar_active_text' => '#ffffff',
        'theme_sidebar_border' => '#8aa9c4',
        'theme_button_primary_bg' => '#4f46e5',
        'theme_button_primary_text' => '#ffffff',
    ];

    /**
     * برچسب فارسی فیلدهای تم (برای فرم تنظیمات پلتفرم).
     *
     * @return array<string, string>
     */
    public static function themeKeyLabels(): array
    {
        return [
            'theme_primary' => 'رنگ اصلی',
            'theme_secondary' => 'رنگ ثانویه',
            'theme_menu_bg' => 'پس‌زمینه منوی بالا',
            'theme_menu_text' => 'متن منوی بالا',
            'theme_menu_link_active' => 'لینک فعال منوی بالا',
            'theme_title_color' => 'رنگ عناوین',
            'theme_link_color' => 'رنگ لینک‌ها',
            'theme_link_hover' => 'رنگ لینک هنگام قرارگیری ماوس',
            'theme_sidebar_bg' => 'نوار کناری: بالای گرادیان',
            'theme_sidebar_bg_bottom' => 'نوار کناری: پایین گرادیان',
            'theme_sidebar_text' => 'نوار کناری: متن لینک‌ها',
            'theme_sidebar_muted' => 'نوار کناری: برچسب گروه‌ها',
            'theme_sidebar_accent' => 'نوار کناری: تأکید و آیکن فعال',
            'theme_sidebar_active_bg' => 'نوار کناری: پس‌زمینه آیتم فعال',
            'theme_sidebar_active_text' => 'نوار کناری: متن آیتم فعال',
            'theme_sidebar_border' => 'نوار کناری: حاشیه',
            'theme_button_primary_bg' => 'پس‌زمینه دکمه اصلی',
            'theme_button_primary_text' => 'متن دکمه اصلی',
        ];
    }

    public static function themeKeyLabel(string $key): string
    {
        return self::themeKeyLabels()[$key] ?? $key;
    }

    public const FONT_KEYS = [
        'font_body' => 'Vazirmatn, Tahoma, sans-serif',
        'font_title' => 'Vazirmatn, Tahoma, sans-serif',
        'font_size_base' => '16',
        'font_size_title' => '1.25',
        'font_custom_1_name' => '',
        'font_custom_1_url' => '',
        'font_custom_2_name' => '',
        'font_custom_2_url' => '',
        'font_custom_3_name' => '',
        'font_custom_3_url' => '',
        'font_custom_4_name' => '',
        'font_custom_4_url' => '',
        'font_custom_5_name' => '',
        'font_custom_5_url' => '',
    ];

    /** حداکثر حجم آپلود تصویر (کیلوبایت). پیش‌فرض ۵۱۲۰ = ۵ مگابایت */
    public const UPLOAD_KEYS = [
        'upload_image_max_kb' => '5120',
    ];

    /** اعلان خودکار انقضا: پشتیبانی، هاست، دامنه — ۱ ماه قبل و هر ۱۰ روز یک‌بار */
    public const AUTO_EXPIRY_KEYS = [
        'auto_notify_support_expiry' => '1',
        'auto_notify_host_expiry' => '1',
        'auto_notify_domain_expiry' => '1',
    ];

    /** پیامک: فعال/غیرفعال و ارسال به ادمین پلتفرم در هشدار انقضا */
    public const SMS_EXPIRY_KEYS = [
        'sms_expiry_enabled' => '1', // ارسال پیامک همراه اعلان انقضا
        'expiry_sms_to_platform_admin' => '0',
    ];

    /** ویجت راهنما: فعال/غیرفعال، موقعیت، پالت رنگ */
    public const GUIDE_WIDGET_KEYS = [
        'guide_widget_enabled' => '1',
        'guide_widget_position' => 'bottom-right', // bottom-right | bottom-left | top-right | top-left
        'admin_notif_widget_position' => 'bottom-right', // موقعیت ویجت اعلان ادمین در پنل سایت مشتری
        'guide_widget_btn_bg' => '#2563eb',
        'guide_widget_btn_text' => '#ffffff',
        'guide_widget_panel_bg' => '#ffffff',
        'guide_widget_panel_header_bg' => '#f9fafb',
        'guide_widget_panel_text' => '#111827',
    ];

    /** تنظیمات ایمیل (Enterprise) */
    public const EMAIL_KEYS = [
        'email_driver' => 'smtp',
        'email_host' => '',
        'email_port' => '587',
        'email_username' => '',
        'email_password' => '',
        'email_encryption' => 'tls',
        'email_from_address' => '',
        'email_from_name' => '',
    ];

    /** تنظیمات پیامک (Enterprise) */
    public const SMS_KEYS = [
        'sms_driver' => 'melipayamak',
        'sms_api_key' => '',
        'sms_line_number' => '',
    ];

    /** کلیدهای API خارجی (Enterprise) — رمزنگاری‌شده ذخیره می‌شوند */
    public const API_KEYS_KEYS = [
        'api_google_maps_key' => '',
        'api_recaptcha_site_key' => '',
        'api_recaptcha_secret_key' => '',
    ];

    /** Feature flags (Enterprise) */
    public const FEATURE_FLAGS_KEYS = [
        'feature_marketplace_sync' => '0',
        'feature_multi_currency' => '0',
        'feature_wallet' => '0',
        'feature_advanced_analytics' => '0',
    ];

    public static function get(string $key, ?string $default = null): string
    {
        $allDefaults = array_merge(
            self::GENERAL_KEYS,
            self::THEME_KEYS,
            self::FONT_KEYS,
            self::UPLOAD_KEYS,
            self::AUTO_EXPIRY_KEYS,
            self::SMS_EXPIRY_KEYS,
            self::GUIDE_WIDGET_KEYS,
            self::EMAIL_KEYS,
            self::SMS_KEYS,
            self::API_KEYS_KEYS,
            self::FEATURE_FLAGS_KEYS
        );
        $default = $default ?? ($allDefaults[$key] ?? '');
        return (string) Setting::get($key, $default);
    }

    public static function allGeneral(): array
    {
        $out = [];
        foreach (array_keys(self::GENERAL_KEYS) as $key) {
            $out[$key] = self::get($key);
        }
        return $out;
    }

    public static function allTheme(): array
    {
        $out = [];
        foreach (array_keys(self::THEME_KEYS) as $key) {
            $out[$key] = self::get($key);
        }
        return $out;
    }

    public static function allFont(): array
    {
        $out = [];
        foreach (array_keys(self::FONT_KEYS) as $key) {
            $out[$key] = self::get($key);
        }
        return $out;
    }

    public static function allUpload(): array
    {
        $out = [];
        foreach (array_keys(self::UPLOAD_KEYS) as $key) {
            $out[$key] = self::get($key);
        }
        return $out;
    }

    public static function allAutoExpiry(): array
    {
        $out = [];
        foreach (array_keys(self::AUTO_EXPIRY_KEYS) as $key) {
            $out[$key] = self::get($key);
        }
        return $out;
    }

    public static function allGuideWidget(): array
    {
        $out = [];
        foreach (array_keys(self::GUIDE_WIDGET_KEYS) as $key) {
            $out[$key] = self::get($key);
        }
        return $out;
    }

    public static function isAutoNotifySupportExpiryEnabled(): bool
    {
        return (string) self::get('auto_notify_support_expiry', '1') === '1';
    }

    public static function isAutoNotifyHostExpiryEnabled(): bool
    {
        return (string) self::get('auto_notify_host_expiry', '1') === '1';
    }

    public static function isAutoNotifyDomainExpiryEnabled(): bool
    {
        return (string) self::get('auto_notify_domain_expiry', '1') === '1';
    }

    public static function isExpirySmsEnabled(): bool
    {
        return (string) self::get('sms_expiry_enabled', '1') === '1';
    }

    public static function isExpirySmsToPlatformAdminEnabled(): bool
    {
        return (string) self::get('expiry_sms_to_platform_admin', '0') === '1';
    }

    public static function allSmsExpiry(): array
    {
        $out = [];
        foreach (array_keys(self::SMS_EXPIRY_KEYS) as $key) {
            $out[$key] = self::get($key);
        }
        return $out;
    }

    public static function guideWidgetEnabled(): bool
    {
        return (string) self::get('guide_widget_enabled', '1') === '1';
    }

    public static function guideWidgetPosition(): string
    {
        $p = (string) self::get('guide_widget_position', 'bottom-right');
        return in_array($p, ['bottom-right', 'bottom-left', 'top-right', 'top-left'], true) ? $p : 'bottom-right';
    }

    /** کلاس‌های CSS برای موقعیت دکمهٔ ویجت (fixed) */
    public static function guideWidgetPositionClasses(): string
    {
        $map = [
            'bottom-right' => 'bottom-6 right-6',
            'bottom-left' => 'bottom-6 left-6',
            'top-right' => 'top-6 right-6',
            'top-left' => 'top-6 left-6',
        ];
        return $map[self::guideWidgetPosition()] ?? 'bottom-6 right-6';
    }

    /** موقعیت ویجت اعلان ادمین در پنل سایت مشتری */
    public static function adminNotifWidgetPosition(): string
    {
        $p = (string) self::get('admin_notif_widget_position', 'bottom-right');
        return in_array($p, ['bottom-right', 'bottom-left', 'top-right', 'top-left'], true) ? $p : 'bottom-right';
    }

    public static function adminNotifWidgetPositionClasses(): string
    {
        $map = [
            'bottom-right' => 'bottom-6 right-6',
            'bottom-left' => 'bottom-6 left-6',
            'top-right' => 'top-20 right-6',
            'top-left' => 'top-20 left-6',
        ];
        return $map[self::adminNotifWidgetPosition()] ?? 'bottom-6 right-6';
    }

    /** مسیر ذخیره‌شده را به مسیر نسبی به دیسک public نرمال می‌کند (مثلاً برای وجود فایل و ساخت URL) */
    public static function normalizeStoragePath(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }
        $path = str_replace('\\', '/', trim($path));
        foreach (['storage/app/public/', 'storage/public/', 'storage/', 'public/storage/'] as $prefix) {
            if (stripos($path, $prefix) === 0) {
                $path = substr($path, strlen($prefix));
            }
        }
        $path = ltrim($path, '/');
        if (preg_match('#^[A-Za-z]:/#', $path) || str_starts_with($path, '/')) {
            if (preg_match('#(?:^|/)storage/app/public/(.+)$#', $path, $m) || preg_match('#(?:^|/)public/storage/(.+)$#', $path, $m)) {
                $path = $m[1];
            }
        }
        return $path;
    }

    /** حداکثر حجم مجاز آپلود تصویر به کیلوبایت (عدد صحیح) */
    public static function uploadImageMaxKb(): int
    {
        $v = (int) self::get('upload_image_max_kb', '5120');
        return $v > 0 ? $v : 5120;
    }

    public static function isRegistrationOpen(): bool
    {
        return (string) self::get('registration_open', '1') === '1';
    }

    public static function platformName(): string
    {
        return self::get('platform_name', 'پلتفرم SaaS');
    }

    public static function logoUrl(): ?string
    {
        $path = trim((string) self::get('platform_logo', ''));
        if ($path === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $path) || str_starts_with($path, '/')) {
            return $path;
        }
        $path = self::normalizeStoragePath($path);
        if ($path === '') {
            return null;
        }
        return '/storage/' . $path;
    }

    public static function allEmail(): array
    {
        $out = [];
        foreach (array_keys(self::EMAIL_KEYS) as $key) {
            $out[$key] = self::get($key);
        }
        return $out;
    }

    public static function allSms(): array
    {
        $out = [];
        foreach (array_keys(self::SMS_KEYS) as $key) {
            $out[$key] = self::get($key);
        }
        return $out;
    }

    public static function allApiKeys(): array
    {
        $out = [];
        foreach (array_keys(self::API_KEYS_KEYS) as $key) {
            $out[$key] = self::get($key);
        }
        return $out;
    }

    public static function allFeatureFlags(): array
    {
        $out = [];
        foreach (array_keys(self::FEATURE_FLAGS_KEYS) as $key) {
            $out[$key] = self::get($key);
        }
        return $out;
    }

    public static function isFeatureEnabled(string $feature): bool
    {
        return (string) self::get($feature, '0') === '1';
    }

    public static function save(array $data): void
    {
        $allKeys = array_merge(
            self::GENERAL_KEYS,
            self::THEME_KEYS,
            self::FONT_KEYS,
            self::UPLOAD_KEYS,
            self::AUTO_EXPIRY_KEYS,
            self::SMS_EXPIRY_KEYS,
            self::GUIDE_WIDGET_KEYS,
            self::EMAIL_KEYS,
            self::SMS_KEYS,
            self::API_KEYS_KEYS,
            self::FEATURE_FLAGS_KEYS
        );
        foreach ($allKeys as $key => $default) {
            if (array_key_exists($key, $data)) {
                Setting::updateOrCreate(['key' => $key], ['value' => (string) $data[$key]]);
            }
        }
    }

    public const LOGO_ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    public const LOGO_ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
    ];

    public static function storeLogo(UploadedFile $file): string
    {
        // Defense-in-depth: validate extension and MIME inside the method itself,
        // independent of controller-level validation rules.
        \App\Services\Infrastructure\StorageManager::validateUpload(
            $file,
            self::LOGO_ALLOWED_EXTENSIONS,
            self::LOGO_ALLOWED_MIMES
        );

        /** @var \App\Services\Infrastructure\StorageManager $storage */
        $storage = app(\App\Services\Infrastructure\StorageManager::class);

        // Delete old logo via StorageManager (consistent boundary).
        $old        = self::get('platform_logo', '');
        $uploadDisk = config('filesystems.upload_disk', 'public');
        if ($old !== '') {
            $storage->deleteIfExists($old, $uploadDisk);
        }

        $path = $storage->storeUploadedFile($file, 'platform', $uploadDisk);

        Setting::updateOrCreate(['key' => 'platform_logo'], ['value' => $path]);
        return $path;
    }
}
