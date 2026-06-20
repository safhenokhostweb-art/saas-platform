<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // themes table — add category/license/badge/installs columns
        if (!Schema::hasColumn('themes', 'category')) {
            DB::statement("ALTER TABLE themes ADD COLUMN category VARCHAR(60) NULL AFTER type");
            DB::statement("ALTER TABLE themes ADD INDEX themes_category_index (category)");
        }
        if (!Schema::hasColumn('themes', 'license_type')) {
            DB::statement("ALTER TABLE themes ADD COLUMN license_type VARCHAR(20) NOT NULL DEFAULT 'free' AFTER marketplace_price");
        }
        if (!Schema::hasColumn('themes', 'marketplace_badge')) {
            DB::statement("ALTER TABLE themes ADD COLUMN marketplace_badge VARCHAR(60) NULL AFTER license_type");
        }
        if (!Schema::hasColumn('themes', 'installs_count')) {
            DB::statement("ALTER TABLE themes ADD COLUMN installs_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER marketplace_badge");
        }

        // theme_marketplace table — quality scores + commercial fields
        if (!Schema::hasColumn('theme_marketplace', 'design_score')) {
            DB::statement("ALTER TABLE theme_marketplace ADD COLUMN design_score TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER sales_count");
            DB::statement("ALTER TABLE theme_marketplace ADD COLUMN commerce_score TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER design_score");
            DB::statement("ALTER TABLE theme_marketplace ADD COLUMN performance_score TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER commerce_score");
            DB::statement("ALTER TABLE theme_marketplace ADD COLUMN completeness_score TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER performance_score");
            DB::statement("ALTER TABLE theme_marketplace ADD COLUMN marketplace_score TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER completeness_score");
        }
        if (!Schema::hasColumn('theme_marketplace', 'quality_issues')) {
            DB::statement("ALTER TABLE theme_marketplace ADD COLUMN quality_issues JSON NULL AFTER marketplace_score");
            DB::statement("ALTER TABLE theme_marketplace ADD COLUMN last_scored_at TIMESTAMP NULL AFTER quality_issues");
        }
        if (!Schema::hasColumn('theme_marketplace', 'annual_price')) {
            DB::statement("ALTER TABLE theme_marketplace ADD COLUMN annual_price INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_scored_at");
            DB::statement("ALTER TABLE theme_marketplace ADD COLUMN lifetime_price INT UNSIGNED NOT NULL DEFAULT 0 AFTER annual_price");
        }
        if (!Schema::hasColumn('theme_marketplace', 'is_new_release')) {
            DB::statement("ALTER TABLE theme_marketplace ADD COLUMN is_new_release TINYINT(1) NOT NULL DEFAULT 0 AFTER lifetime_price");
            DB::statement("ALTER TABLE theme_marketplace ADD COLUMN is_bestseller TINYINT(1) NOT NULL DEFAULT 0 AFTER is_new_release");
        }
        if (!Schema::hasColumn('theme_marketplace', 'preview_url')) {
            DB::statement("ALTER TABLE theme_marketplace ADD COLUMN preview_url VARCHAR(500) NULL AFTER is_bestseller");
        }
        if (!Schema::hasColumn('theme_marketplace', 'category_tags')) {
            DB::statement("ALTER TABLE theme_marketplace ADD COLUMN category_tags JSON NULL AFTER preview_url");
        }
        if (!Schema::hasColumn('theme_marketplace', 'supported_modules_json')) {
            DB::statement("ALTER TABLE theme_marketplace ADD COLUMN supported_modules_json VARCHAR(500) NULL AFTER category_tags");
        }
    }

    public function down(): void
    {
        Schema::table('themes', function ($table) {
            $table->dropColumn(array_filter(
                ['category', 'license_type', 'marketplace_badge', 'installs_count'],
                fn($col) => Schema::hasColumn('themes', $col)
            ));
        });

        Schema::table('theme_marketplace', function ($table) {
            $table->dropColumn(array_filter([
                'design_score', 'commerce_score', 'performance_score',
                'completeness_score', 'marketplace_score',
                'quality_issues', 'last_scored_at',
                'annual_price', 'lifetime_price',
                'is_new_release', 'is_bestseller',
                'preview_url', 'category_tags', 'supported_modules_json',
            ], fn($col) => Schema::hasColumn('theme_marketplace', $col)));
        });
    }
};
