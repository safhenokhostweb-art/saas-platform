<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 18-E — Part 6: seo_advanced_audits only ever stored
 * title_length/description_length (integers), never the actual text —
 * fine for the original length-threshold check, but the new
 * duplicate-title/duplicate-description technical check needs the real
 * text to compare across pages. Additive/nullable — existing rows simply
 * have no text until their next audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_advanced_audits', function (Blueprint $table) {
            $table->string('title', 255)->nullable()->after('page_url');
            $table->string('description', 500)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('seo_advanced_audits', function (Blueprint $table) {
            $table->dropColumn(['title', 'description']);
        });
    }
};
