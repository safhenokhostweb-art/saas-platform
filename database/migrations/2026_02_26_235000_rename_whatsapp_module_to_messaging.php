<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('modules')
            ->where('slug', 'whatsapp')
            ->update([
                'title' => 'پاسخگویی شبکه‌ها',
                'description' => 'ویجت دکمه/لینک تماس واتساپ، تلگرام و سایر شبکه‌های اجتماعی.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('modules')
            ->where('slug', 'whatsapp')
            ->update([
                'title' => 'واتساپ',
                'description' => 'ویجت دکمه/لینک تماس واتساپ.',
                'updated_at' => now(),
            ]);
    }
};
