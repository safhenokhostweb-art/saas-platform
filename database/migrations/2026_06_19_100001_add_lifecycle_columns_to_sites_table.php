<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('lifecycle_status', 30)->default('active')->after('is_active');
            $table->timestamp('lifecycle_changed_at')->nullable()->after('lifecycle_status');
            $table->timestamp('grace_period_started_at')->nullable()->after('lifecycle_changed_at');
            $table->timestamp('grace_period_ends_at')->nullable()->after('grace_period_started_at');
            $table->timestamp('suspended_at')->nullable()->after('grace_period_ends_at');
            $table->string('suspended_reason', 500)->nullable()->after('suspended_at');
            $table->timestamp('archived_at')->nullable()->after('suspended_reason');
            $table->string('archive_path', 1000)->nullable()->after('archived_at');
            $table->timestamp('restore_requested_at')->nullable()->after('archive_path');
            $table->timestamp('lifecycle_deleted_at')->nullable()->after('restore_requested_at');

            $table->index('lifecycle_status', 'sites_lifecycle_status_idx');
            $table->index('grace_period_ends_at', 'sites_grace_ends_idx');
            $table->index('suspended_at', 'sites_suspended_at_idx');
            $table->index('archived_at', 'sites_archived_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropIndex('sites_lifecycle_status_idx');
            $table->dropIndex('sites_grace_ends_idx');
            $table->dropIndex('sites_suspended_at_idx');
            $table->dropIndex('sites_archived_at_idx');
            $table->dropColumn([
                'lifecycle_status', 'lifecycle_changed_at',
                'grace_period_started_at', 'grace_period_ends_at',
                'suspended_at', 'suspended_reason',
                'archived_at', 'archive_path',
                'restore_requested_at', 'lifecycle_deleted_at',
            ]);
        });
    }
};
