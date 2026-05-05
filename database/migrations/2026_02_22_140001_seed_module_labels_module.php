<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::table('modules')->where('slug', 'module_labels')->exists()) {
            return;
        }
        DB::table('modules')->insert([
            'slug' => 'module_labels',
            'title' => 'عنوان و جزئیات ماژول‌ها',
            'description' => 'ویرایش عنوان و توضیح نمایشی ماژول‌ها و آیتم‌های منو (مثلاً «کپچا» به «کد امنیتی»).',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('modules')->where('slug', 'module_labels')->delete();
    }
};
