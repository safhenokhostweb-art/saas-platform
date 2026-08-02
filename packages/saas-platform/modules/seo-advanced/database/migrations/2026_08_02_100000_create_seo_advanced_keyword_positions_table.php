<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 18-E — Part 3: normalized Keyword Position History, replacing
 * the flat `position_history` JSON column (kept, untouched, for
 * backward compatibility — SeoAdvancedKeyword::recordPosition() now
 * writes to both). One row per {keyword, url, search_engine, date}
 * observation — required so a keyword can be tracked against more than
 * one URL/search engine over time, which a single JSON blob on the
 * keyword row could never represent cleanly.
 *
 * site_id is a plain column (no DB-level FK) — matches this module's
 * own existing convention on seo_advanced_keywords.site_id, not the
 * Tickets-style FK convention CRM uses (see Phase 19 audit finding —
 * both conventions coexist in this platform; this module stays
 * consistent with itself).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_advanced_keyword_positions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->foreignId('keyword_id')->constrained('seo_advanced_keywords')->cascadeOnDelete();
            $table->string('url', 500)->nullable();
            $table->unsignedSmallInteger('position')->nullable(); // null = not found in top N
            $table->date('recorded_at');
            $table->string('search_engine', 30)->default('google'); // google|bing|manual|...
            $table->string('source', 30)->default('manual'); // which RankProviderInterface implementation recorded this
            $table->timestamps();

            $table->index(['keyword_id', 'recorded_at']);
            $table->index(['site_id', 'search_engine']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_advanced_keyword_positions');
    }
};
