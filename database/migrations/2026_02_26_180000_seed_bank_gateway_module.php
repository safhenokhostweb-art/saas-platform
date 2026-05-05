<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::table('modules')->where('slug', 'bank_gateway')->exists()) {
            return;
        }
        DB::table('modules')->insert([
            'slug' => 'bank_gateway',
            'title' => 'درگاه بانکی',
            'description' => 'درگاه پرداخت بانک‌های ایرانی (زرین‌پال، آی‌دی‌پی و …). قابل تخصیص به هر سایت. تنظیمات و مرچنت کد از پنل مدیریت.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('modules')->where('slug', 'bank_gateway')->delete();
    }
};
