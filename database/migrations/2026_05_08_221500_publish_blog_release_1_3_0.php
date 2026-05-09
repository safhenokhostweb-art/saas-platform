<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('modules') || !Schema::hasTable('module_releases')) {
            return;
        }

        $module = DB::table('modules')->where('slug', 'blog')->first();
        if (!$module) {
            return;
        }

        $version = '1.3.0';
        $now = now();
        $changelog = implode("\n", [
            'انتشار حرفه‌ای 1.3.0 برای ماژول وبلاگ:',
            'تاریخ انتشار (شمسی): '.\App\Helpers\Jalali::formatFa($now, 'Y/m/d'),
            '- جریان تحریریهٔ حرفه‌ای برای tenant اضافه شد: پیش‌نویس، بازبینی، انتشار فوری و زمان‌بندی انتشار.',
            '- کیفیت محتوای وبلاگ با fallback هوشمند SEO، کنترل slug/permalink و نمایش وضعیت محتوا در ادمین تقویت شد.',
            '- صفحهٔ فرانت وبلاگ با متادیتای سازگارتر (canonical/description)، مطالب منتخب و related fallback حرفه‌ای ارتقا یافت.',
        ]);

        DB::table('module_releases')->updateOrInsert(
            [
                'module_id' => $module->id,
                'version' => $version,
            ],
            [
                'release_type' => 'minor',
                'changelog' => $changelog,
                'distributed_at' => $now,
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        DB::table('modules')
            ->where('id', $module->id)
            ->update([
                'version' => $version,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('modules') || !Schema::hasTable('module_releases')) {
            return;
        }

        $module = DB::table('modules')->where('slug', 'blog')->first();
        if (!$module) {
            return;
        }

        DB::table('module_releases')
            ->where('module_id', $module->id)
            ->where('version', '1.3.0')
            ->delete();
    }
};
