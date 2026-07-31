<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 18 — Part 4/6: one row per audited page, written by SeoAuditor.
 * Distinct from Core's seo_page_scores (which nothing ever writes to —
 * see the audit report) — this module's audit engine is real and self-
 * contained, never reads or writes Core's table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_advanced_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->string('page_url', 500);
            $table->unsignedSmallInteger('title_length')->nullable();
            $table->unsignedSmallInteger('description_length')->nullable();
            $table->json('missing_meta')->nullable();
            $table->json('missing_alt_images')->nullable();
            $table->unsignedTinyInteger('score')->default(0);
            $table->timestamp('audited_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'page_url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_advanced_audits');
    }
};
