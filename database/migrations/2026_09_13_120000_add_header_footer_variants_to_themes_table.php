<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * THEME-03 (Full Product Commercial Audit, TASK 2A): the single canonical
 * Header/Footer authority becomes Theme-owned NAMED VARIANTS, not just one
 * flat block list per Theme. Additive only — `header_blocks`/`footer_blocks`
 * are untouched and remain the "no variants authored yet" implicit single
 * variant (see Theme::getHeaderVariants()/getFooterVariants()), so every
 * existing Theme keeps working with zero data migration required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->json('header_variants')->nullable()->after('header_blocks');
            $table->json('footer_variants')->nullable()->after('footer_blocks');
        });
    }

    public function down(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->dropColumn(['header_variants', 'footer_variants']);
        });
    }
};
