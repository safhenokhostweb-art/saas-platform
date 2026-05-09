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

        $module = DB::table('modules')->where('slug', 'menu')->first();
        if (!$module) {
            return;
        }

        $version = '1.1.0';
        $now = now();
        $changelog = implode("\n", [
            'ارتقای حرفه‌ای ماژول منو (1.1.0):',
            '- اعتبارسنجی نوع آیتم منو سخت‌گیرانه‌تر شد (custom URL, internal page, category).',
            '- نمایش وضعیت حرفه‌ای ماژول منو در پنل مشتری اضافه شد (نسخه، حالت local/platform، شمار آیتم‌ها).',
            '- همگام‌سازی و مشاهده لاگ تغییرات منو برای تیم و مشتری شفاف‌تر شد.',
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

        $module = DB::table('modules')->where('slug', 'menu')->first();
        if (!$module) {
            return;
        }

        DB::table('module_releases')
            ->where('module_id', $module->id)
            ->where('version', '1.1.0')
            ->delete();
    }
};
