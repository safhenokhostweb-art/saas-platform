<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theme_fleet_updates', function (Blueprint $table) {
            $table->unsignedBigInteger('theme_id')->nullable()->change();
            $table->unsignedBigInteger('to_release_id')->nullable()->change();

            if (!Schema::hasColumn('theme_fleet_updates', 'update_type')) {
                $table->string('update_type', 32)->default('theme')->after('site_id');
                $table->index('update_type');
            }

            if (!Schema::hasColumn('theme_fleet_updates', 'component_versions')) {
                $table->json('component_versions')->nullable()->after('to_release_id');
            }

            if (!Schema::hasColumn('theme_fleet_updates', 'changelog_summary')) {
                $table->text('changelog_summary')->nullable()->after('reconciliation_summary');
            }

            if (!Schema::hasColumn('theme_fleet_updates', 'is_rollback')) {
                $table->boolean('is_rollback')->default(false)->after('status');
                $table->index('is_rollback');
            }

            if (!Schema::hasColumn('theme_fleet_updates', 'rollback_from_update_id')) {
                $table->foreignId('rollback_from_update_id')
                    ->nullable()
                    ->after('is_rollback')
                    ->constrained('theme_fleet_updates')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('theme_fleet_updates', function (Blueprint $table) {
            if (Schema::hasColumn('theme_fleet_updates', 'rollback_from_update_id')) {
                $table->dropForeign(['rollback_from_update_id']);
                $table->dropColumn('rollback_from_update_id');
            }

            if (Schema::hasColumn('theme_fleet_updates', 'is_rollback')) {
                $table->dropIndex(['is_rollback']);
                $table->dropColumn('is_rollback');
            }

            if (Schema::hasColumn('theme_fleet_updates', 'changelog_summary')) {
                $table->dropColumn('changelog_summary');
            }

            if (Schema::hasColumn('theme_fleet_updates', 'component_versions')) {
                $table->dropColumn('component_versions');
            }

            if (Schema::hasColumn('theme_fleet_updates', 'update_type')) {
                $table->dropIndex(['update_type']);
                $table->dropColumn('update_type');
            }
        });
    }
};
