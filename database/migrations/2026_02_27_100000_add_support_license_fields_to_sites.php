<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فیلدهای مدیریت لایسنس پشتیبانی: تاریخ شروع، سطح پشتیبانی.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->timestamp('support_starts_at')->nullable()->after('support_expires_at');
            $table->string('support_level', 32)->default('basic')->after('support_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['support_starts_at', 'support_level']);
        });
    }
};
