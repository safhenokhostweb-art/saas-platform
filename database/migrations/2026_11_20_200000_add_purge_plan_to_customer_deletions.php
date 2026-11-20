<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-090 — site full purge.
 *
 * plan / plan_sha256: the immutable inventory the operator confirmed in the
 * dry-run; stages read their destructive targets from it.
 * options: the operator's choices (delete_backups, delete_media).
 * manual_actions: resources deliberately left for a human
 * (status complete_with_warnings).
 * requested_ip / requested_user_agent: audit of who started it from where.
 *
 * site_id stays a plain indexed column (no foreign key), so the deletion and
 * its logs outlive the site row they removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_deletions', function (Blueprint $table) {
            $table->json('plan')->nullable()->after('verify_report');
            $table->char('plan_sha256', 64)->nullable()->after('plan');
            $table->json('options')->nullable()->after('plan_sha256');
            $table->json('manual_actions')->nullable()->after('options');
            $table->string('requested_ip', 45)->nullable()->after('manual_actions');
            $table->string('requested_user_agent', 255)->nullable()->after('requested_ip');
        });
    }

    public function down(): void
    {
        Schema::table('customer_deletions', function (Blueprint $table) {
            $table->dropColumn(['plan', 'plan_sha256', 'options', 'manual_actions', 'requested_ip', 'requested_user_agent']);
        });
    }
};
