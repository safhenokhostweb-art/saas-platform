<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * افزودن ماژول حذف ۴ صفر از پول ملی ایران.
 * طبق سیاست اصلاح واحد پول، قیمت‌ها بر ۱۰٬۰۰۰ تقسیم می‌شوند.
 */
return new class extends Migration {
    public function up(): void
    {
        $exists = DB::table('modules')->where('slug', 'currency_reform')->first();
        if (!$exists) {
            DB::table('modules')->insert([
                'slug' => 'currency_reform',
                'title' => 'حذف ۴ صفر پول ملی',
                'description' => 'اصلاح واحد پول ایران؛ قیمت‌های محصولات بر اساس قالب جدید (تقسیم بر ۱۰٬۰۰۰) نمایش و ذخیره می‌شوند.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('modules')->where('slug', 'currency_reform')->update([
                'title' => 'حذف ۴ صفر پول ملی',
                'description' => 'اصلاح واحد پول ایران؛ قیمت‌های محصولات بر اساس قالب جدید (تقسیم بر ۱۰٬۰۰۰) نمایش و ذخیره می‌شوند.',
                'is_active' => true,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // حذف نکن — ممکن است سایت‌ها به آن وابسته باشند
    }
};
