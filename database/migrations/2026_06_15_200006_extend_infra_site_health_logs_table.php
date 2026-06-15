<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infra_site_health_logs', function (Blueprint $table) {
            $table->unsignedInteger('failed_jobs')->nullable()->after('queue_status');
            $table->tinyInteger('disk_used_percent')->unsigned()->nullable()->after('failed_jobs');
            $table->tinyInteger('memory_used_percent')->unsigned()->nullable()->after('disk_used_percent');
            $table->date('domain_expires_at')->nullable()->after('memory_used_percent');
            $table->smallInteger('domain_days_remaining')->nullable()->after('domain_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('infra_site_health_logs', function (Blueprint $table) {
            $table->dropColumn([
                'failed_jobs', 'disk_used_percent', 'memory_used_percent',
                'domain_expires_at', 'domain_days_remaining',
            ]);
        });
    }
};
