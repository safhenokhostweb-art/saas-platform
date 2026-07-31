<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 18 — Part 6: one row per site — site-wide SEO defaults (Meta
 * Management, Canonical, OpenGraph, Schema.org). Deliberately named
 * seo_advanced_* (not seo_settings/seo_metas) to avoid any collision with
 * Core's existing, untouched `seo_metas`/`seo_redirects`/`seo_page_scores`
 * tables (see the Phase 18 audit report, Part 1) — this module owns its
 * own storage entirely, never writes to Core's SEO tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_advanced_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('meta_keywords', 500)->nullable();
            $table->string('robots_directive', 100)->default('index,follow');
            $table->string('canonical_url', 500)->nullable();
            $table->string('og_title', 255)->nullable();
            $table->string('og_description', 500)->nullable();
            $table->string('og_image_url', 500)->nullable();
            $table->string('schema_type', 32)->default('website'); // organization|local_business|website
            $table->string('organization_name', 255)->nullable();
            $table->string('organization_logo_url', 500)->nullable();
            $table->string('business_address', 500)->nullable();
            $table->string('business_phone', 64)->nullable();
            $table->timestamps();

            $table->unique('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_advanced_settings');
    }
};
