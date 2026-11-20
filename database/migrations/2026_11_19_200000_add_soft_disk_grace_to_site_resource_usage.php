<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1 (2026-11-19): the 7-day soft-disk grace period existed only as text in
 * a notification. These columns make it a real state machine, with its own
 * flag instead of sharing alerted_80 with the hard-disk 80% warning:
 *   soft_exceeded_at        — when usage first crossed the soft quota (grace starts)
 *   alerted_soft            — the "soft quota exceeded" alert was sent
 *   soft_grace_escalated_at — the grace expired and was escalated (once)
 * Additive and nullable: no existing row or query changes meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_resource_usage', function (Blueprint $table) {
            if (! Schema::hasColumn('site_resource_usage', 'soft_exceeded_at')) {
                $table->timestamp('soft_exceeded_at')->nullable();
            }
            if (! Schema::hasColumn('site_resource_usage', 'alerted_soft')) {
                $table->boolean('alerted_soft')->default(false);
            }
            if (! Schema::hasColumn('site_resource_usage', 'soft_grace_escalated_at')) {
                $table->timestamp('soft_grace_escalated_at')->nullable();
            }
        });
    }

    /**
     * Intentionally a no-op: the columns are additive, nullable/defaulted and
     * unused by older code, so leaving them on rollback is harmless, and a
     * destructive down() would need an owner migration review.
     */
    public function down(): void
    {
    }
};
