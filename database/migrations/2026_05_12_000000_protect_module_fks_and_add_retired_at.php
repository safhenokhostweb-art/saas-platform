<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 / Wave 4 — P0-2
 *
 * هدف:
 * 1) جلوگیری از حذف فیزیکی ماژول وقتی هنوز توسط سایت‌ها استفاده می‌شود.
 *    FK های module_id در سه جدول site_modules, site_module_purchases, module_licenses
 *    از cascadeOnDelete به restrictOnDelete تبدیل می‌شوند.
 * 2) افزودن `retired_at` به جدول modules برای «بازنشسته‌سازی نرم» ماژول‌ها.
 *    ماژول بازنشسته از مارکت‌پلیس و فهرست‌های اختصاص دستی حذف می‌شود ولی
 *    site_modules فعلی، خریدها و لایسنس‌های موجود **سالم باقی می‌مانند**.
 *
 * این migration کاملاً additive و reversible است.
 * هیچ ردیفی در هیچ جدولی پاک یا اصلاح نمی‌شود.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('site_modules')) {
            Schema::table('site_modules', function (Blueprint $table) {
                try {
                    $table->dropForeign(['module_id']);
                } catch (\Throwable $e) {
                }
            });
            Schema::table('site_modules', function (Blueprint $table) {
                $table->foreign('module_id')
                    ->references('id')->on('modules')
                    ->restrictOnDelete();
            });
        }

        if (Schema::hasTable('site_module_purchases')) {
            Schema::table('site_module_purchases', function (Blueprint $table) {
                try {
                    $table->dropForeign(['module_id']);
                } catch (\Throwable $e) {
                }
            });
            Schema::table('site_module_purchases', function (Blueprint $table) {
                $table->foreign('module_id')
                    ->references('id')->on('modules')
                    ->restrictOnDelete();
            });
        }

        if (Schema::hasTable('module_licenses')) {
            Schema::table('module_licenses', function (Blueprint $table) {
                try {
                    $table->dropForeign(['module_id']);
                } catch (\Throwable $e) {
                }
            });
            Schema::table('module_licenses', function (Blueprint $table) {
                $table->foreign('module_id')
                    ->references('id')->on('modules')
                    ->restrictOnDelete();
            });
        }

        if (Schema::hasTable('modules') && ! Schema::hasColumn('modules', 'retired_at')) {
            Schema::table('modules', function (Blueprint $table) {
                $table->timestamp('retired_at')->nullable()->after('is_active');
                $table->index('retired_at', 'modules_retired_at_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modules') && Schema::hasColumn('modules', 'retired_at')) {
            Schema::table('modules', function (Blueprint $table) {
                try {
                    $table->dropIndex('modules_retired_at_index');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('retired_at');
            });
        }

        if (Schema::hasTable('module_licenses')) {
            Schema::table('module_licenses', function (Blueprint $table) {
                try {
                    $table->dropForeign(['module_id']);
                } catch (\Throwable $e) {
                }
            });
            Schema::table('module_licenses', function (Blueprint $table) {
                $table->foreign('module_id')
                    ->references('id')->on('modules')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('site_module_purchases')) {
            Schema::table('site_module_purchases', function (Blueprint $table) {
                try {
                    $table->dropForeign(['module_id']);
                } catch (\Throwable $e) {
                }
            });
            Schema::table('site_module_purchases', function (Blueprint $table) {
                $table->foreign('module_id')
                    ->references('id')->on('modules')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('site_modules')) {
            Schema::table('site_modules', function (Blueprint $table) {
                try {
                    $table->dropForeign(['module_id']);
                } catch (\Throwable $e) {
                }
            });
            Schema::table('site_modules', function (Blueprint $table) {
                $table->foreign('module_id')
                    ->references('id')->on('modules')
                    ->cascadeOnDelete();
            });
        }
    }
};
