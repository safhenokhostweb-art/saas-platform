<?php

namespace App\Helpers;

use App\Models\Setting;

/**
 * ماژول‌های مختص پلتفرم (فقط در پنل ادمین saas-platform).
 * این ماژول‌ها به سایت مشتری اختصاص داده نمی‌شوند و فقط امکانات داخلی پلتفرم هستند.
 */
class PlatformModules
{
    /** کلید => [عنوان، توضیح، مسیرها] */
    public const MODULES = [
        'notifications' => [
            'title' => 'اعلان',
            'description' => 'ارسال اعلان به کاربران یا ادمین سایت‌های مشتری. قالب‌های خوش‌آمدگویی به‌صورت خودکار به سایت‌های جدید ارسال می‌شوند.',
            'routes' => [
                'ارسال اعلان' => 'admin.sites.notifications.create-bulk',
                'قالب‌های خوش‌آمدگویی' => 'admin.notification-templates.index',
            ],
        ],
    ];

    public static function all(): array
    {
        return self::MODULES;
    }

    public static function isEnabled(string $key): bool
    {
        return (string) Setting::get('platform_module_' . $key, '1') === '1';
    }

    public static function getTitle(string $key): string
    {
        return self::MODULES[$key]['title'] ?? $key;
    }

    public static function getDescription(string $key): string
    {
        return self::MODULES[$key]['description'] ?? '';
    }

    public static function getRoutes(string $key): array
    {
        return self::MODULES[$key]['routes'] ?? [];
    }

    public static function getRoute(string $key): ?string
    {
        $routes = self::getRoutes($key);
        return !empty($routes) ? reset($routes) : null;
    }
}
