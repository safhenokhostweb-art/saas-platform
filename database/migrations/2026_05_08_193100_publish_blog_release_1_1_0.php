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

        $version = '1.1.0';
        $now = now();
        $changelog = implode("\n", [
            'بهبود حرفه‌ای ماژول وبلاگ:',
            'تاریخ انتشار (شمسی): '.\App\Helpers\Jalali::formatFa($now, 'Y/m/d'),
            '- ارتقای تجربهٔ فرانت با محتوای مرتبط، پربازدیدترین مطالب و خلاصهٔ خوانا در لیست.',
            '- تقویت انتشار ادمین با تولید slug یکتا و ساخت خودکار خلاصه برای بهبود SEO و یکپارچگی URL.',
            '- نمایش واضح‌تر نسخهٔ نصب‌شده/قابل دریافت در پنل مشتری و لاگ نسخه در پنل پلتفرم.',
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
            ->where('version', '1.1.0')
            ->delete();
    }
};
