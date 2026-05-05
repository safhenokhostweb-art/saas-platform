<?php

namespace App\Helpers;

use App\Models\Module;
use App\Models\ModuleLabel;

/**
 * عنوان و توضیح نمایشی ماژول‌ها/آیتم‌های منو.
 * ادمین اصلی: siteId = null (برچسب‌های پلتفرم)
 * مشتری: siteId = شناسه سایت (برچسب‌های همان سایت)
 */
class ModuleLabels
{
    public static function getTitle(string $key, ?int $siteId = null): string
    {
        $label = ModuleLabel::forSite($siteId)->where('key', $key)->first();
        if ($label && $label->custom_title !== null && trim($label->custom_title) !== '') {
            return trim($label->custom_title);
        }
        if (isset(AdminMenu::MENU_KEYS[$key])) {
            return AdminMenu::MENU_KEYS[$key];
        }
        if (isset(CustomerMenu::MENU_KEYS[$key])) {
            return CustomerMenu::MENU_KEYS[$key];
        }
        $module = Module::where('slug', $key)->first();
        return $module ? $module->title : $key;
    }

    public static function getDescription(string $key, ?int $siteId = null): string
    {
        $label = ModuleLabel::forSite($siteId)->where('key', $key)->first();
        if ($label && $label->custom_description !== null && trim($label->custom_description) !== '') {
            return trim($label->custom_description);
        }
        $module = Module::where('slug', $key)->first();
        return $module ? (string) $module->description : '';
    }

    /** همهٔ کلیدهای قابل ویرایش برای پنل ادمین اصلی */
    public static function keysForAdmin(): array
    {
        return array_keys(AdminMenu::MENU_KEYS);
    }

    /** همهٔ کلیدهای قابل ویرایش برای پنل مشتری */
    public static function keysForCustomer(): array
    {
        return array_keys(CustomerMenu::MENU_KEYS);
    }

    /** برچسب پیش‌فرض برای یک کلید (بدون سفارشی‌سازی) */
    public static function defaultTitle(string $key): string
    {
        if (isset(AdminMenu::MENU_KEYS[$key])) {
            return AdminMenu::MENU_KEYS[$key];
        }
        if (isset(CustomerMenu::MENU_KEYS[$key])) {
            return CustomerMenu::MENU_KEYS[$key];
        }
        $module = Module::where('slug', $key)->first();
        return $module ? $module->title : $key;
    }

    public static function defaultDescription(string $key): string
    {
        $module = Module::where('slug', $key)->first();
        return $module ? (string) $module->description : '';
    }
}
