<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infra_backup_runs', function (Blueprint $table) {
            // Composite index covering the most common DR query pattern:
            // WHERE site_id IS NULL AND backup_type = 'database' AND status = 'success'
            // ORDER BY started_at DESC
            $table->index(
                ['site_id', 'backup_type', 'status', 'started_at'],
                'infra_backup_runs_dr_query_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('infra_backup_runs', function (Blueprint $table) {
            $table->dropIndex('infra_backup_runs_dr_query_idx');
        });
    }
};
