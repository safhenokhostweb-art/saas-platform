<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::table('modules')->where('slug', 'captcha')->exists()) {
            return;
        }
        DB::table('modules')->insert([
            'slug' => 'captcha',
            'title' => 'کپچا',
            'description' => 'کپچای عدد فارسی، معمولی و hCaptcha برای فرم‌ها. قابل فعال/غیرفعال از پنل ادمین و تنظیم به‌ازای هر سایت.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('modules')->where('slug', 'captcha')->delete();
    }
};
