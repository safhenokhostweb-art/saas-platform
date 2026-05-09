<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('modules') || ! Schema::hasTable('module_releases')) {
            return;
        }

        $module = DB::table('modules')->where('slug', 'shop')->first();
        if (! $module) {
            return;
        }

        $version = '2.0.0';
        $now = now();
        $changelog = implode("\n", [
            'انتشار نسخهٔ 2.0.0 ماژول فروشگاه (افزودنی‌های حرفه‌ای):',
            'تاریخ انتشار (شمسی): '.\App\Helpers\Jalali::formatFa($now, 'Y/m/d'),
            '- تنوع محصول (SKU، قیمت و موجودی per variant)، گالری تصاویر، بستهٔ محصول (Bundle) با اعتبارسنجی موجودی اجزا.',
            '- مشخصات ساخت‌یافته، برچسب‌ها، محصولات مرتبط / upsell / cross-sell، پیش‌فروش و اعلام موجودشدن.',
            '- وضعیت پیش‌نویس/انتشار، سئو (canonical، OG)، JSON-LD غنی‌تر، شمارندهٔ بازدید، رویداد add_to_cart، لاگ تغییر قیمت/موجودی.',
            '- علاقه‌مندی، مقایسه (حداکثر ۴)، نظرات خریداران با تأیید ادمین، تب‌های صفحهٔ محصول و فیلتر فروشگاه بر اساس برچسب/ویژگی.',
            '- سبد خرید با کلید ردیف product:variant، ثبت product_variant_id در آیتم سفارش، سازگاری کوپن با ساختار جدید سبد.',
        ]);

        DB::table('module_releases')->updateOrInsert(
            [
                'module_id' => $module->id,
                'version' => $version,
            ],
            [
                'release_type' => 'major',
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
        if (! Schema::hasTable('modules') || ! Schema::hasTable('module_releases')) {
            return;
        }

        $module = DB::table('modules')->where('slug', 'shop')->first();
        if (! $module) {
            return;
        }

        DB::table('module_releases')
            ->where('module_id', $module->id)
            ->where('version', '2.0.0')
            ->delete();
    }
};
