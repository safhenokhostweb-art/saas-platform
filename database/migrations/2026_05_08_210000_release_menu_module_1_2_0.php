<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * انتشار نسخهٔ ۱.۲.۰ ماژول منو برای سایت‌های مشتری (changelog + module_releases).
 */
return new class extends Migration
{
    public function up(): void
    {
        $moduleId = DB::table('modules')->where('slug', 'menu')->value('id');
        if (! $moduleId) {
            return;
        }

        DB::table('modules')->where('id', $moduleId)->update([
            'version' => '1.2.0',
            'description' => 'منوی چندسطحی، مگامنو، UTM، پیش‌نویس/انتشار، نسخه‌گردانی، drag & drop، API JSON، منطقهٔ موبایل/ثانویه.',
            'updated_at' => now(),
        ]);

        $exists = DB::table('module_releases')
            ->where('module_id', $moduleId)
            ->where('version', '1.2.0')
            ->exists();
        if ($exists) {
            return;
        }

        DB::table('module_releases')->insert([
            'module_id' => $moduleId,
            'version' => '1.2.0',
            'release_type' => 'minor',
            'changelog' => 'نسخه ۱.۲.۰: عمق قابل تنظیم، مگامنو و ستون‌ها، UTM و nofollow، پیش‌نویس و زمان‌بندی انتشار، نسخه‌های پشتیبان و بازگردانی، کشیدن درخت، کپی شاخه، پیش‌نمایش با توکن، API ‎/site-api/menus، ادغام منوی دیتابیس با حالت local، لاگ تغییرات.',
            'distributed_at' => now(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $moduleId = DB::table('modules')->where('slug', 'menu')->value('id');
        if (! $moduleId) {
            return;
        }

        DB::table('module_releases')
            ->where('module_id', $moduleId)
            ->where('version', '1.2.0')
            ->delete();

        DB::table('modules')->where('id', $moduleId)->update([
            'version' => '1.0.0',
            'description' => 'منوی چندسطحی، مگا منو، لینک پویا، drag & drop، منو هدر و فوتر.',
            'updated_at' => now(),
        ]);
    }
};
