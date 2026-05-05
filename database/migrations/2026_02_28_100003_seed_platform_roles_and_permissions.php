<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions')) {
            return;
        }

        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super_admin', 'guard_name' => 'web', 'description' => 'دسترسی کامل به پلتفرم'],
            ['name' => 'Staff', 'slug' => 'staff', 'guard_name' => 'web', 'description' => 'کارمند پلتفرم با دسترسی محدود'],
            ['name' => 'Reseller', 'slug' => 'reseller', 'guard_name' => 'web', 'description' => 'نماینده با دسترسی به سایت‌های خود'],
        ];

        foreach ($roles as $r) {
            DB::table('roles')->insertOrIgnore(array_merge($r, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $permissions = [
            ['name' => 'داشبورد', 'slug' => 'dashboard', 'module_slug' => 'dashboard'],
            ['name' => 'کاربران پلتفرم', 'slug' => 'users', 'module_slug' => 'users'],
            ['name' => 'مشتری‌ها', 'slug' => 'customers', 'module_slug' => 'customers'],
            ['name' => 'سایت‌ها', 'slug' => 'sites', 'module_slug' => 'sites'],
            ['name' => 'فرم‌ها', 'slug' => 'forms', 'module_slug' => 'forms'],
            ['name' => 'گزارش ارسال فرم‌ها', 'slug' => 'forms_submissions', 'module_slug' => 'forms'],
            ['name' => 'فیلدهای پروفایل', 'slug' => 'profile_fields', 'module_slug' => 'profile'],
            ['name' => 'تنظیمات فرم پروفایل', 'slug' => 'settings_profile', 'module_slug' => 'profile'],
            ['name' => 'دسترسی و منو', 'slug' => 'access', 'module_slug' => 'access'],
            ['name' => 'تنظیمات کپچا', 'slug' => 'captcha', 'module_slug' => 'captcha'],
            ['name' => 'لاگ فعالیت', 'slug' => 'activity_log', 'module_slug' => 'activity_log'],
            ['name' => 'تنظیمات عمومی پلتفرم', 'slug' => 'platform_settings', 'module_slug' => 'platform'],
            ['name' => 'صفحات ثابت', 'slug' => 'pages', 'module_slug' => 'pages'],
            ['name' => 'عنوان ماژول‌ها', 'slug' => 'module_labels', 'module_slug' => 'modules'],
            ['name' => 'نمایندگان', 'slug' => 'resellers', 'module_slug' => 'resellers'],
            ['name' => 'تسویه کمیسیون', 'slug' => 'reseller_commissions', 'module_slug' => 'resellers'],
            ['name' => 'محصولات فروشگاه', 'slug' => 'products', 'module_slug' => 'products'],
            ['name' => 'پلن‌های پشتیبانی', 'slug' => 'support_plans', 'module_slug' => 'support'],
            ['name' => 'درخواست‌های تسویه', 'slug' => 'payout_requests', 'module_slug' => 'resellers'],
            ['name' => 'تیکت‌های پشتیبانی', 'slug' => 'support_tickets', 'module_slug' => 'support'],
            ['name' => 'درگاه‌های پرداخت', 'slug' => 'payment_gateways', 'module_slug' => 'payment'],
        ];

        foreach ($permissions as $p) {
            DB::table('permissions')->insertOrIgnore(array_merge($p, [
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // Staff permissions: assign via UI or tinker. Super Admin bypasses checks.
    }

    public function down(): void
    {
        if (Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')->truncate();
        }
        if (Schema::hasTable('role_user')) {
            DB::table('role_user')->truncate();
        }
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->truncate();
        }
        if (Schema::hasTable('roles')) {
            DB::table('roles')->truncate();
        }
    }
};
