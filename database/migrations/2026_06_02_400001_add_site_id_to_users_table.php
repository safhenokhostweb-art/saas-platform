<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * هر کاربر می‌تواند به یک site (سایت مشتری) تعلق داشته باشد.
 * - ادمین اصلی: site_id = null (به هیچ سایتی تعلق ندارد)
 * - ادمین سایت مشتری: site_id = ID سایت مربوطه
 * - کاربر نهایی: site_id = ID سایتی که در آن ثبت‌نام کرده
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('site_id')->nullable()->after('is_admin');
            $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['site_id']);
            $table->dropColumn('site_id');
        });
    }
};
