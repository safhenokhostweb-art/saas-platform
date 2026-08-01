<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 20 — Part 3: Sales Kanban requires recording exactly when a deal
 * last moved stage (distinct from `updated_at`, which changes on any
 * field edit, not just a stage move). Additive/nullable — existing rows
 * simply have no recorded stage-change history until their next move.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table) {
            $table->timestamp('stage_changed_at')->nullable()->after('stage_id');
        });
    }

    public function down(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table) {
            $table->dropColumn('stage_changed_at');
        });
    }
};
