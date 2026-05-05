<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::table('modules')->where('slug', 'notifications')->exists()) {
            return;
        }
        DB::table('modules')->insert([
            'slug' => 'notifications',
            'title' => 'اعلان‌ها',
            'description' => 'اعلان‌های تولبار با تصویر برای نمایش به کاربران سایت.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('modules')->where('slug', 'notifications')->delete();
    }
};
