<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SUPPORT-SUITE-01 — Section K/L/N
 * Feature Request workflow: fr_status, votes table, subscribers table
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Add feature-request columns to tickets ─────────────────────────
        Schema::table('tickets', function (Blueprint $table) {
            // ticket type: support (default) or feature_request
            $table->string('type', 30)->default('support')->after('category');

            // FR-specific 10-state workflow (null for non-FR tickets)
            $table->string('fr_status', 40)->nullable()->after('type');

            // When FR status was last updated
            $table->timestamp('fr_status_updated_at')->nullable()->after('fr_status');

            // Demand/impact scoring (editable by SaaS admin)
            $table->unsignedTinyInteger('fr_impact_score')->default(0)->after('fr_status_updated_at');

            // Estimated delivery quarter (e.g. "Q3 2026")
            $table->string('fr_estimated_quarter', 20)->nullable()->after('fr_impact_score');

            // Index for fast FR queries
            $table->index(['type', 'fr_status'], 'idx_tickets_type_fr_status');
        });

        // ── 2. Feature-request votes ──────────────────────────────────────────
        Schema::create('ticket_votes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');

            // voter may be a User (customer) or anonymous (mobile only)
            $table->unsignedBigInteger('voter_id')->nullable();
            $table->string('voter_mobile', 20)->nullable();
            $table->string('voter_name', 100)->nullable();

            $table->timestamps();

            // one vote per user per ticket
            $table->unique(['ticket_id', 'voter_id'],     'uq_ticket_vote_user');
            $table->unique(['ticket_id', 'voter_mobile'], 'uq_ticket_vote_mobile');
            $table->index('ticket_id');
        });

        // ── 3. Feature-request subscribers ───────────────────────────────────
        Schema::create('ticket_fr_subscribers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('customer_mobile', 20)->nullable();
            $table->string('customer_name', 100)->nullable();

            $table->timestamps();

            $table->unique(['ticket_id', 'user_id'],         'uq_fr_sub_user');
            $table->unique(['ticket_id', 'customer_mobile'], 'uq_fr_sub_mobile');
            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_fr_subscribers');
        Schema::dropIfExists('ticket_votes');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('idx_tickets_type_fr_status');
            $table->dropColumn(['type', 'fr_status', 'fr_status_updated_at', 'fr_impact_score', 'fr_estimated_quarter']);
        });
    }
};
