<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * افزودن فیلد features (JSON) به جداول resellers و sites
     * برای ذخیره‌سازی feature flags مخصوص هر tenant.
     */
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            if (! Schema::hasColumn('resellers', 'features')) {
                $table->json('features')->nullable()->after('subdomain');
            }
        });

        Schema::table('sites', function (Blueprint $table) {
            if (! Schema::hasColumn('sites', 'features')) {
                $table->json('features')->nullable()->after('theme_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->dropColumn('features');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('features');
        });
    }
};
