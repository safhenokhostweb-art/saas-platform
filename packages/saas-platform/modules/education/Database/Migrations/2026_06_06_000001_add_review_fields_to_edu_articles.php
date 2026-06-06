<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EDU-16 — Human Review & Approval Workflow.
 *
 * Adds three audit columns to edu_articles:
 *
 *   reviewed_by_id  — FK to users table; null until a human reviewer acts.
 *   reviewed_at     — Timestamp of the review decision.
 *   review_note     — Optional rejection reason (required on reject in application layer).
 *
 * A composite index on (auto_generated, status, reviewed_at) covers the
 * primary review-queue query:
 *   WHERE auto_generated = 1 AND status = 'draft'
 *
 * All columns are nullable: existing rows (human-authored articles) have no
 * reviewer and the constraint is enforced at the application / policy layer,
 * not the database layer.
 *
 * Compatible with MySQL 5.7+, MySQL 8, MariaDB, and SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edu_articles', function (Blueprint $table) {
            $table->unsignedBigInteger('reviewed_by_id')->nullable()->after('author_id');
            $table->timestamp('reviewed_at')->nullable()->after('published_at');
            $table->string('review_note', 1000)->nullable()->after('reviewed_at');

            // Covers: WHERE auto_generated = 1 AND status = 'draft'
            // Used by the review queue list and analytics queries.
            $table->index(
                ['auto_generated', 'status', 'reviewed_at'],
                'edu_articles_review_queue_idx'
            );

            // FK to users — nullOnDelete so deleting a reviewer does not
            // cascade-delete articles.
            $table->foreign('reviewed_by_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('edu_articles', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by_id']);
            $table->dropIndex('edu_articles_review_queue_idx');
            $table->dropColumn(['reviewed_by_id', 'reviewed_at', 'review_note']);
        });
    }
};
