<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن ستون version به جدول modules برای ردیابی نسخهٔ ماژول.
 * فرمت: semver (مثلاً 1.0.0).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->string('version', 32)->default('1.0.0')->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
