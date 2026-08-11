<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11 — customer-facing "request a custom motion" needs a free-text
 * field for what the customer is asking for. Additive only; nothing else
 * on motion_bespoke_commissions changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('motion_bespoke_commissions', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('motion_bespoke_commissions', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
