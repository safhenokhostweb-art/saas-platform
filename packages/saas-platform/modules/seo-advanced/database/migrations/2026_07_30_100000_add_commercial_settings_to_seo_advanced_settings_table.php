<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 18-D — Part 2: additive-only columns closing the gaps found in
 * the Part 1 audit — Twitter Card default, a Canonical Policy (replacing
 * the ambiguity of a single site-wide canonical_url "default" — which
 * never made sense per-page — with an explicit strategy, while keeping
 * canonical_url itself for backward compatibility as the 'custom'
 * strategy's value), and configurable title/description length
 * thresholds (previously hardcoded in SeoAuditor). All nullable/defaulted
 * so every existing row and every existing test keeps working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_advanced_settings', function (Blueprint $table) {
            $table->string('twitter_card_type', 32)->default('summary')->after('og_image_url');
            $table->string('canonical_strategy', 32)->default('auto')->after('canonical_url'); // auto|force_https|custom
            $table->unsignedSmallInteger('title_min_length')->nullable()->after('meta_title');
            $table->unsignedSmallInteger('title_max_length')->nullable()->after('title_min_length');
            $table->unsignedSmallInteger('description_min_length')->nullable()->after('meta_description');
            $table->unsignedSmallInteger('description_max_length')->nullable()->after('description_min_length');
        });

        // Backward compatibility: a row that already had a canonical_url
        // set before this migration was relying on it being used verbatim
        // (the only behavior that existed) — defaulting its strategy to
        // 'auto' would silently change that site's live canonical output.
        // Only rows with no canonical_url at all get the new 'auto' default.
        DB::table('seo_advanced_settings')
            ->whereNotNull('canonical_url')
            ->where('canonical_url', '!=', '')
            ->update(['canonical_strategy' => 'custom']);
    }

    public function down(): void
    {
        Schema::table('seo_advanced_settings', function (Blueprint $table) {
            $table->dropColumn([
                'twitter_card_type', 'canonical_strategy',
                'title_min_length', 'title_max_length',
                'description_min_length', 'description_max_length',
            ]);
        });
    }
};
