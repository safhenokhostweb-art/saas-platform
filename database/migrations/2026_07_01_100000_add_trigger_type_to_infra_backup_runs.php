<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infra_backup_runs', function (Blueprint $table) {
            $table->string('trigger_type', 32)->default('manual')
                  ->after('backup_type')
                  ->comment('manual|daily|weekly|monthly|before_upgrade|before_delete|before_restore');
            $table->string('sha256', 64)->nullable()->after('file_size_mb')
                  ->comment('SHA-256 checksum of the backup file');
        });
    }

    public function down(): void
    {
        Schema::table('infra_backup_runs', function (Blueprint $table) {
            $table->dropColumn(['trigger_type', 'sha256']);
        });
    }
};
