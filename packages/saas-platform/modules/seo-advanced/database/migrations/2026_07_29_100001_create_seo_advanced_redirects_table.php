<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 18 — Part 6/7: per-site redirect rules, managed entirely by this
 * module's own admin UI. Independent of Core's existing seo_redirects
 * table (which stays untouched — see the audit report).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_advanced_redirects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->string('from_path', 500);
            $table->string('to_path', 500);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['site_id', 'from_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_advanced_redirects');
    }
};
