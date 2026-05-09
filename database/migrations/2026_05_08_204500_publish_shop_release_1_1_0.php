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

        $module = DB::table('modules')->where('slug', 'shop')->first();
        if (!$module) {
            return;
        }

        $version = '1.1.0';
        $now = now();
        $changelog = implode("\n", [
            'انتشار حرفه‌ای 1.1.0 برای ماژول فروشگاه:',
            'تاریخ انتشار (شمسی): '.\App\Helpers\Jalali::formatFa($now, 'Y/m/d'),
            '- لیست فروشگاه در tenant از فیلتر بازهٔ قیمت مبتنی بر effective price و مرتب‌سازی استاندارد پشتیبانی می‌کند.',
            '- کنترل موجودی هنگام افزودن/ویرایش سبد و checkout سخت‌گیرانه‌تر شد تا از سفارش بیش‌از‌موجودی جلوگیری شود.',
            '- ثبت سفارش فروشگاه به‌صورت تراکنشی انجام می‌شود و کاهش موجودی کالاهای محدود پس از ثبت سفارش اتمیک است.',
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

        $module = DB::table('modules')->where('slug', 'shop')->first();
        if (!$module) {
            return;
        }

        DB::table('module_releases')
            ->where('module_id', $module->id)
            ->where('version', '1.1.0')
            ->delete();
    }
};
