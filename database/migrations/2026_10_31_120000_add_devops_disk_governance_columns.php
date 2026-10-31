<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DevOps resource governor — measured disk usage and thin-provisioned quotas.
 *
 * Extends the LIFECYCLE-2 tables instead of adding a parallel quota store:
 * site_resource_limits stays the single source of truth for per-site limits,
 * site_resource_usage for the latest measurement. Every column is nullable or
 * defaulted, so existing rows and code paths are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_resource_limits', function (Blueprint $table) {
            // null = fall back to config('resource_governance.disk.*')
            $table->unsignedBigInteger('disk_soft_limit_bytes')->nullable()->after('bandwidth_limit_bytes');
            $table->unsignedBigInteger('disk_hard_limit_bytes')->nullable()->after('disk_soft_limit_bytes');
            $table->decimal('cpu_cores_limit', 5, 2)->nullable()->after('ram_weight');
            $table->unsignedBigInteger('memory_limit_bytes')->nullable()->after('cpu_cores_limit');
        });

        Schema::table('site_resource_usage', function (Blueprint $table) {
            // Real `du` of the site's web root (storage_bytes stays the DB-derived estimate).
            $table->unsignedBigInteger('disk_usage_bytes')->nullable()->after('storage_bytes');
            $table->string('danger_level', 16)->default('normal')->after('disk_usage_bytes');
            $table->timestamp('disk_audited_at')->nullable()->after('danger_level');
            $table->string('disk_audit_error', 500)->nullable()->after('disk_audited_at');

            $table->index('danger_level');
        });
    }

    public function down(): void
    {
        Schema::table('site_resource_usage', function (Blueprint $table) {
            $table->dropIndex(['danger_level']);
            $table->dropColumn(['disk_usage_bytes', 'danger_level', 'disk_audited_at', 'disk_audit_error']);
        });

        Schema::table('site_resource_limits', function (Blueprint $table) {
            $table->dropColumn(['disk_soft_limit_bytes', 'disk_hard_limit_bytes', 'cpu_cores_limit', 'memory_limit_bytes']);
        });
    }
};
