<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن ستون installed_version به site_modules برای ردیابی نسخهٔ نصب‌شده در هر سایت.
 * nullable: تا قبل از اولین گزارش/توزیع، مقدار ندارد.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('site_modules', function (Blueprint $table) {
            $table->string('installed_version', 32)->nullable()->after('is_active');
            $table->timestamp('version_reported_at')->nullable()->after('installed_version');
        });
    }

    public function down(): void
    {
        Schema::table('site_modules', function (Blueprint $table) {
            $table->dropColumn(['installed_version', 'version_reported_at']);
        });
    }
};
