<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('platform_site_notifications', function (Blueprint $table) {
            $table->string('audience')->default('public')->after('order'); // public = کاربر نهایی، admin = ادمین سایت
        });
    }

    public function down(): void
    {
        Schema::table('platform_site_notifications', function (Blueprint $table) {
            $table->dropColumn('audience');
        });
    }
};
