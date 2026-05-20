<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * افزودن ماژول پشتیبانی و تیکت‌ها به جدول modules.
 * این ماژول قابلیت اختصاص به سایت مشتری را دارد.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('modules')->where('slug', 'support')->first();
        if (!$exists) {
            DB::table('modules')->insert([
                'slug' => 'support',
                'title' => 'پشتیبانی و تیکت‌ها',
                'description' => 'سیستم تیکت پشتیبانی برای مشتریان. مشتری می‌تواند تیکت ثبت کند، پاسخ دریافت کند و وضعیت را پیگیری نماید.',
                'is_active' => true,
                'type' => 'core',
                'free_assignable' => true,
                'category' => 'support',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('modules')->where('slug', 'support')->update([
                'title' => 'پشتیبانی و تیکت‌ها',
                'description' => 'سیستم تیکت پشتیبانی برای مشتریان. مشتری می‌تواند تیکت ثبت کند، پاسخ دریافت کند و وضعیت را پیگیری نماید.',
                'is_active' => true,
                'type' => 'core',
                'free_assignable' => true,
                'category' => 'support',
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // حذف نمی‌کنیم — ممکن است سایت‌ها به آن وابسته باشند
    }
};
