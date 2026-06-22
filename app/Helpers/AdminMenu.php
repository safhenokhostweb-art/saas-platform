<?php
namespace App\Helpers;

use App\Models\Setting;
use Illuminate\Support\Facades\Gate;

class AdminMenu
{
    /** کلیدهای منوی مربوط به Wizard (فقط Super Admin) */
    public const WIZARD_MENU_KEYS = ['theme_builder'];
    public const MENU_KEYS = [
        'dashboard' => 'داشبورد',
        'users' => 'کاربران پلتفرم',
        'customers' => 'مشتریان',
        'sites' => 'سایت‌ها',
        'site_license' => 'صدور لایسنس برای سایت',
        'forms' => 'فرم‌ها',
        'profile_fields' => 'فیلدهای پروفایل',
        'settings_profile' => 'تنظیمات پروفایل',
        'access' => 'دسترسی و منو',
        'captcha' => 'تنظیمات کپچا',
        'activity_log' => 'لاگ فعالیت',
        'platform_settings' => 'تنظیمات عمومی پلتفرم',
        'pages' => 'برگه‌ها',
        'module_labels' => 'عنوان و جزئیات ماژول‌ها',
        'resellers' => 'نمایندگان',
        'reseller_commissions' => 'تسویه کمیسیون',
        'products' => 'محصولات فروشگاه',
        'support_plans' => 'پلن‌های پشتیبانی',
        'payout_requests' => 'درخواست‌های تسویه',
        'support_tickets' => 'تیکت‌های پشتیبانی',
        'payment_gateways' => 'درگاه‌های پرداخت',
        'modules' => 'ماژول‌ها',
        'module_licenses' => 'لایسنس‌های ماژول',
        'licenses' => 'لایسنس‌ها',
        'updates' => 'داشبورد بروزرسانی‌ها',
        'theme_builder'     => 'ساختار تم (Theme Builder)',
        'theme_templates'   => 'کتابخانه قالب‌ها',
        'theme_packs'       => 'پک‌های تم',
        'zip_import'        => 'ورود قالب از ZIP',
        'theme_manager'     => 'مدیریت تم (Theme Engine)',
        'app_releases'   => 'دانلود اپلیکیشن',
        'education'      => 'مرکز آموزش',
        'infrastructure'       => 'مدیریت زیرساخت',
        'monitoring_center'   => 'مرکز سلامت',
        'incidents'           => 'مدیریت حوادث',
        'notification_center' => 'مرکز اطلاع‌رسانی',
        'module_adoption'    => 'پذیرش ماژول‌ها',
        'kb'                 => 'مقالات دانش‌نامه',
        'lifecycle'              => 'چرخه عمر سایت‌ها',
        'resource_governance'    => 'حاکمیت منابع',
    ];

    /** مسیر هر آیتم منو */
    public const MENU_ROUTES = [
        'dashboard' => 'admin.dashboard',
        'users' => 'admin.users.index',
        'customers' => 'admin.customers.index',
        'sites' => 'admin.sites.index',
        'site_license' => 'admin.sites.index',
        'forms' => 'admin.forms.index',
        'profile_fields' => 'admin.profile-fields.index',
        'settings_profile' => 'admin.settings.profile-form',
        'access' => 'admin.access.index',
        'captcha' => 'admin.captcha.index',
        'activity_log' => 'admin.activity-log.index',
        'platform_settings' => 'admin.platform-settings.index',
        'pages' => 'admin.pages.index',
        'module_labels' => 'admin.module-labels.index',
        'resellers' => 'admin.resellers.index',
        'reseller_commissions' => 'admin.reseller-commissions.index',
        'products' => 'admin.products.index',
        'support_plans' => 'admin.support-plans.index',
        'payout_requests' => 'admin.payout-requests.index',
        'support_tickets' => 'admin.support-tickets.index',
        'payment_gateways' => 'admin.payment-gateways.index',
        'modules' => 'admin.modules.index',
        'module_licenses' => 'admin.module-licenses.index',
        'licenses' => 'admin.licenses.index',
        'updates' => 'admin.updates.index',
        'theme_builder'   => 'admin.theme-builder.index',
        'theme_templates' => 'admin.theme-templates.index',
        'theme_packs'     => 'admin.theme-packs.index',
        'zip_import'      => 'admin.theme-builder.zip-import.form',
        'theme_manager'   => 'admin.theme-manager.index',
        'app_releases'   => 'admin.app-releases.index',
        'education'      => 'admin.education.index',
        'infrastructure'       => 'admin.infrastructure.overview',
        'monitoring_center'   => 'admin.infrastructure.monitoring.index',
        'incidents'           => 'admin.incidents.index',
        'notification_center' => 'admin.notification-center.index',
        'module_adoption'    => 'admin.module-adoption.index',
        'kb'                 => 'admin.kb.index',
        'lifecycle'              => 'admin.lifecycle.overview',
        'resource_governance'    => 'admin.resource-governance.index',
    ];

    /** الگوی روت برای حالت فعال (برای routeIs) */
    public const MENU_ROUTE_PATTERNS = [
        'dashboard' => 'admin.dashboard',
        'users' => 'admin.users.*',
        'customers' => 'admin.customers.*',
        'sites' => 'admin.sites.*',
        'site_license' => 'admin.sites.*',
        'forms' => 'admin.forms.*',
        'profile_fields' => 'admin.profile-fields.*',
        'settings_profile' => 'admin.settings.*',
        'access' => 'admin.access.*',
        'captcha' => 'admin.captcha.*',
        'activity_log' => 'admin.activity-log.*',
        'platform_settings' => 'admin.platform-settings.*',
        'pages' => 'admin.pages.*',
        'module_labels' => 'admin.module-labels.*',
        'resellers' => 'admin.resellers.*',
        'reseller_commissions' => 'admin.reseller-commissions.*',
        'products' => 'admin.products.*',
        'support_plans' => 'admin.support-plans.*',
        'payout_requests' => 'admin.payout-requests.*',
        'support_tickets' => 'admin.support-tickets.*',
        'payment_gateways' => 'admin.payment-gateways.*',
        'modules' => 'admin.modules.*',
        'module_licenses' => 'admin.module-licenses.*',
        'licenses' => 'admin.licenses.*',
        'updates' => 'admin.updates.*',
        'theme_builder'   => 'admin.theme-builder.*',
        'theme_templates' => 'admin.theme-templates.*',
        'theme_packs'     => 'admin.theme-packs.*',
        'zip_import'      => 'admin.theme-builder.zip-import.*',
        'theme_manager'   => 'admin.theme-manager.*',
        'app_releases'   => 'admin.app-releases.*',
        'education'      => 'admin.education.*',
        'infrastructure'       => 'admin.infrastructure.*',
        'monitoring_center'   => 'admin.infrastructure.monitoring.*',
        'incidents'           => 'admin.incidents.*',
        'notification_center' => 'admin.notification-center.*',
        'module_adoption'    => 'admin.module-adoption.*',
        'kb'                 => 'admin.kb.*',
        'lifecycle'              => 'admin.lifecycle.*',
        'resource_governance'    => 'admin.resource-governance.*',
    ];

    /** این آیتم‌ها همیشه در منو نمایش داده می‌شوند (برای جلوگیری از قفل شدن دسترسی) */
    public const ALWAYS_VISIBLE_KEYS = ['access'];

    /**
     * گروه‌های منوی استاندارد SaaS به ترتیب صحیح:
     */
    public static function getGroups(): array
    {
        return [
            'داشبورد'                          => ['dashboard'],
            'مشتریان / کاربران'                 => ['users', 'customers'],
            'سایت‌ها / پروژه‌ها'                => ['sites', 'site_license', 'modules', 'module_licenses', 'licenses', 'updates'],
            'فروش / صورتحساب'                   => ['products', 'support_plans', 'payment_gateways', 'resellers', 'reseller_commissions', 'payout_requests'],
            'پشتیبانی / تیکت‌ها'                => ['support_tickets'],
            'فرم‌ها / برگه‌ها'                  => ['forms', 'profile_fields', 'settings_profile', 'pages'],
            'محتوا / مقالات'                    => ['theme_builder', 'theme_templates', 'theme_packs', 'zip_import', 'education'],
            'تنظیمات سیستم'                     => ['access', 'captcha', 'module_labels', 'platform_settings', 'theme_manager'],
            'اطلاع‌رسانی / ارتباطات'            => ['notification_center'],
            'زیرساخت / DevOps'                  => ['infrastructure', 'monitoring_center', 'incidents', 'lifecycle', 'resource_governance'],
            'بازاریابی / دانش‌محور'             => ['kb'],
            'گزارش‌ها / آمار'                   => ['activity_log', 'module_adoption'],
            'اپلیکیشن‌ها'                        => ['app_releases'],
        ];
    }

    public static function isVisible(string $key): bool
    {
        if (in_array($key, self::ALWAYS_VISIBLE_KEYS, true)) {
            return true;
        }
        return (string) Setting::get('admin_menu_' . $key, '1') === '1';
    }

    /**
     * آیا آیتم منو باید در سایدبار نمایش داده شود؟
     * برای آیتم‌های Wizard (مثل theme_builder) فقط Super Admin دسترسی دارد.
     */
    public static function isMenuItemVisible(string $key): bool
    {
        if (!self::isVisible($key)) {
            return false;
        }
        if (in_array($key, self::WIZARD_MENU_KEYS, true)) {
            return Gate::allows('accessWizard');
        }
        return true;
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
}
