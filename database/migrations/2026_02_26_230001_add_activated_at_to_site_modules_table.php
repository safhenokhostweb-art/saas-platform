<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن activated_at برای ردیابی زمان فعال‌سازی ماژول در هر سایت.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('site_modules', function (Blueprint $table) {
            $table->timestamp('activated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_modules', function (Blueprint $table) {
            $table->dropColumn('activated_at');
        });
    }
};
