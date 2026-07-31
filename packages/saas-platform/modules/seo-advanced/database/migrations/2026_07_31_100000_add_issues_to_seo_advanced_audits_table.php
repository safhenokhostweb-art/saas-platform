<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 18-D — Part 3: adds a structured `issues` column (array of
 * {code, severity, message, suggested_fix}) alongside the existing flat
 * `missing_meta` strings — additive and nullable, so every pre-existing
 * audit row and every existing test reading `missing_meta` keeps working
 * unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_advanced_audits', function (Blueprint $table) {
            $table->json('issues')->nullable()->after('missing_alt_images');
        });
    }

    public function down(): void
    {
        Schema::table('seo_advanced_audits', function (Blueprint $table) {
            $table->dropColumn('issues');
        });
    }
};
