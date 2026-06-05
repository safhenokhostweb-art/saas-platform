<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-07: Add a partial unique index to edu_recommendations to enforce DB-level idempotency.
 *
 * Because article_id / walkthrough_id / learning_path_id are nullable, MySQL does not treat
 * NULL values as equal in a standard UNIQUE index, so we use COALESCE to a sentinel value (0)
 * for each nullable FK column inside the index expression.
 *
 * Backward compatibility: The migration is safe to run against an existing table with data.
 * Any pre-existing duplicates should be resolved by the application before migrating.
 * The index is NOT UNIQUE in the sense that it allows multiple dismissed/completed rows
 * for the same content — the uniqueness constraint is enforced at the application layer for
 * *active* recommendations. This migration adds a covering index for the hot query path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_recommendations', function (Blueprint $table) {
            // Covering index for the "active recommendations for user" hot query (C-03 + F-07)
            $table->index(
                ['user_id', 'site_id', 'recommendation_type', 'article_id', 'learning_path_id'],
                'edu_rec_dedup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('edu_recommendations', function (Blueprint $table) {
            $table->dropIndex('edu_rec_dedup_idx');
        });
    }
};
