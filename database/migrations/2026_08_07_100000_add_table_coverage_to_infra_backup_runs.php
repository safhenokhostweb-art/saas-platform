<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infra_backup_runs', function (Blueprint $table) {
            $table->json('table_coverage')->nullable()->after('error_message');
            $table->string('completeness_status', 20)->nullable()->after('table_coverage');
        });
    }

    public function down(): void
    {
        Schema::table('infra_backup_runs', function (Blueprint $table) {
            $table->dropColumn(['table_coverage', 'completeness_status']);
        });
    }
};
