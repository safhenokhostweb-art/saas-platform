<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 20 — Part 6: tracks whether a reminder was already sent for a
 * Task (deadline/overdue) or an Opportunity (no-activity staleness) so
 * the daily `crm:send-task-reminders` command never notifies the same
 * condition twice. Additive/nullable on both existing tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('remind_at');
        });

        Schema::table('crm_opportunities', function (Blueprint $table) {
            $table->timestamp('stale_notified_at')->nullable()->after('stage_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });

        Schema::table('crm_opportunities', function (Blueprint $table) {
            $table->dropColumn('stale_notified_at');
        });
    }
};
