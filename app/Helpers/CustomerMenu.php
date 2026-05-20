<?php
namespace App\Helpers;

use App\Models\Setting;

/**
 * منوی پنل مشتری (کاربر غیرادمین).
 * نمایش آیتم‌ها از طریق تنظیمات customer_menu_* در صفحه دسترسی و منو کنترل می‌شود.
 * روت‌ها همه در محدوده auth هستند و به پنل ادمین وابسته نیستند.
 */
class CustomerMenu
{
    /** فقط داشبورد و پروفایل برای کاربر نهایی (کاربر غیرادمین) */
    public const MENU_KEYS = [
        'dashboard' => 'داشبورد',
        'profile' => 'پروفایل',
        'forms' => 'فرم‌ها',
        'support-tickets' => 'تیکت‌های پشتیبانی',
    ];

    /** مسیر هر آیتم منو (روت‌های قابل دسترسی برای مشتری، بدون پیشوند admin) */
    public const MENU_ROUTES = [
        'dashboard' => 'dashboard',
        'profile' => 'profile.edit',
        'forms' => 'customer.forms.index',
        'support-tickets' => 'customer.support-tickets.index',
    ];

    /** الگوی روت برای حالت فعال (برای routeIs) */
    public const MENU_ROUTE_PATTERNS = [
        'dashboard' => 'dashboard',
        'profile' => 'profile.*',
        'forms' => 'customer.forms.*',
        'support-tickets' => 'customer.support-tickets.*',
    ];

    /** آیتم‌هایی که همیشه برای مشتری نمایش داده می‌شوند */
    public const ALWAYS_VISIBLE_KEYS = ['dashboard'];

    public static function isVisible(string $key): bool
    {
        if (!array_key_exists($key, self::MENU_KEYS)) {
            return false;
        }
        if (in_array($key, self::ALWAYS_VISIBLE_KEYS, true)) {
            return true;
        }
        return (string) Setting::get('customer_menu_' . $key, '1') === '1';
    }

    public static function all(): array
    {
        $out = [];
        foreach (array_keys(self::MENU_KEYS) as $key) {
            $out[$key] = self::isVisible($key);
        }
        return $out;
    }

    public static function getRoute(string $key): string
    {
        return self::MENU_ROUTES[$key] ?? '#';
    }

    public static function isActive(string $key): bool
    {
        $pattern = self::MENU_ROUTE_PATTERNS[$key] ?? null;
        return $pattern && request()->routeIs($pattern);
    }

    /** یک گروه برای منوی مشتری */
    public static function getGroups(): array
    {
        return [
'امکانات' => array_keys(self::MENU_KEYS),
        ];
    }
}
