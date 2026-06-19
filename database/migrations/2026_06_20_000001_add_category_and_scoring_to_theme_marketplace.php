<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add category + license_type + commercial fields to themes
        Schema::table('themes', function (Blueprint $table) {
            $table->string('category', 60)->nullable()->after('type')->index();
            $table->string('license_type', 20)->default('free')->after('marketplace_price'); // free|premium|bundle
            $table->string('marketplace_badge', 60)->nullable()->after('license_type'); // "bestseller"|"new"|"featured"
            $table->unsignedSmallInt('installs_count')->default(0)->after('marketplace_badge');
        });

        // Add scoring + commercial metadata to theme_marketplace
        Schema::table('theme_marketplace', function (Blueprint $table) {
            // Quality scores (0–100)
            $table->unsignedTinyInteger('design_score')->default(0)->after('sales_count');
            $table->unsignedTinyInteger('commerce_score')->default(0)->after('design_score');
            $table->unsignedTinyInteger('performance_score')->default(0)->after('commerce_score');
            $table->unsignedTinyInteger('completeness_score')->default(0)->after('performance_score');
            $table->unsignedTinyInteger('marketplace_score')->default(0)->after('completeness_score');

            // Validation metadata (JSON: list of issues found)
            $table->json('quality_issues')->nullable()->after('marketplace_score');
            $table->timestamp('last_scored_at')->nullable()->after('quality_issues');

            // Commercial fields
            $table->unsignedInteger('annual_price')->default(0)->after('last_scored_at'); // rial
            $table->unsignedInteger('lifetime_price')->default(0)->after('annual_price');  // rial
            $table->boolean('is_new_release')->default(false)->after('lifetime_price');
            $table->boolean('is_bestseller')->default(false)->after('is_new_release');
            $table->string('preview_url', 500)->nullable()->after('is_bestseller');
            $table->json('category_tags')->nullable()->after('preview_url');
            $table->string('supported_modules_json', 500)->nullable()->after('category_tags');
        });
    }

    public function down(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->dropColumn(['category', 'license_type', 'marketplace_badge', 'installs_count']);
        });

        Schema::table('theme_marketplace', function (Blueprint $table) {
            $table->dropColumn([
                'design_score', 'commerce_score', 'performance_score',
                'completeness_score', 'marketplace_score',
                'quality_issues', 'last_scored_at',
                'annual_price', 'lifetime_price',
                'is_new_release', 'is_bestseller',
                'preview_url', 'category_tags', 'supported_modules_json',
            ]);
        });
    }
};
