<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * افزودن ماژول جستجوی پیشرفته به جدول modules.
 * طبق قانون site-modules-from-platform هر ماژول جدید باید در پلتفرم ثبت شود.
 */
return new class extends Migration {
    public function up(): void
    {
        $exists = DB::table('modules')->where('slug', 'advanced_search')->first();
        if (!$exists) {
            DB::table('modules')->insert([
                'slug' => 'advanced_search',
                'title' => 'جستجوی پیشرفته',
                'description' => 'جستجوی یکپارچه در محصولات و مطالب با پیشنهاد زنده، فیلتر و مرتب‌سازی (مشابه دیجی‌کالا).',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('modules')->where('slug', 'advanced_search')->update([
                'title' => 'جستجوی پیشرفته',
                'description' => 'جستجوی یکپارچه در محصولات و مطالب با پیشنهاد زنده، فیلتر و مرتب‌سازی (مشابه دیجی‌کالا).',
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
