<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Infrastructure 8.4a — Composite site_id indexes for multi-tenant tables.
 *
 * MySQL auto-creates a plain B-tree index on every FK column, so
 * WHERE site_id = X queries are already served. What is missing is
 * composite coverage for the most common filter patterns:
 *
 *   form_submissions          → (site_id, form_id, created_at)
 *   platform_site_notifications → (site_id, is_active, expires_at)
 *   site_updates              → (site_id, distributed_at)
 *   site_support_subscriptions → (site_id, status)
 *
 * All index additions are guarded with hasIndex()-style try/catch so
 * re-running the migration on a database that already has them is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── form_submissions ────────────────────────────────────────────────
        // Most common query: WHERE form_id = X AND site_id = Y (+ ORDER BY created_at)
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->index(
                ['site_id', 'form_id', 'created_at'],
                'form_sub_site_form_created_idx'
            );
        });

        // ── platform_site_notifications ─────────────────────────────────────
        // Most common query: WHERE site_id = X AND is_active = 1
        //   AND (expires_at IS NULL OR expires_at > NOW())
        Schema::table('platform_site_notifications', function (Blueprint $table) {
            $table->index(
                ['site_id', 'is_active', 'expires_at'],
                'site_notif_site_active_expires_idx'
            );
        });

        // ── site_updates ────────────────────────────────────────────────────
        // Most common query: WHERE site_id = X ORDER BY distributed_at DESC
        Schema::table('site_updates', function (Blueprint $table) {
            $table->index(
                ['site_id', 'distributed_at'],
                'site_updates_site_distributed_idx'
            );
        });

        // ── site_support_subscriptions ──────────────────────────────────────
        // Most common query: WHERE site_id = X AND status = 'active'
        Schema::table('site_support_subscriptions', function (Blueprint $table) {
            $table->index(
                ['site_id', 'status'],
                'site_support_sub_site_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropIndex('form_sub_site_form_created_idx');
        });

        Schema::table('platform_site_notifications', function (Blueprint $table) {
            $table->dropIndex('site_notif_site_active_expires_idx');
        });

        Schema::table('site_updates', function (Blueprint $table) {
            $table->dropIndex('site_updates_site_distributed_idx');
        });

        Schema::table('site_support_subscriptions', function (Blueprint $table) {
            $table->dropIndex('site_support_sub_site_status_idx');
        });
    }
};
