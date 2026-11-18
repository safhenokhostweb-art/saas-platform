<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            if (!Schema::hasColumn('themes', 'distribution_model')) {
                $table->string('distribution_model', 32)->default('marketplace')->after('status');
                $table->index('distribution_model');
            }
        });

        if (!Schema::hasTable('theme_fleet_updates')) {
            Schema::create('theme_fleet_updates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_id')->constrained()->cascadeOnDelete();
                $table->foreignId('theme_id')->constrained('themes')->restrictOnDelete();
                $table->foreignId('from_release_id')->nullable()->constrained('theme_releases')->nullOnDelete();
                $table->foreignId('to_release_id')->constrained('theme_releases')->restrictOnDelete();
                $table->string('status', 30)->default('pending'); // pending, running, completed, failed
                $table->unsignedInteger('revision_number')->nullable();
                $table->json('reconciliation_summary')->nullable();
                $table->text('failure_reason')->nullable();
                $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['site_id', 'status']);
                $table->index(['theme_id', 'to_release_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_fleet_updates');

        Schema::table('themes', function (Blueprint $table) {
            if (Schema::hasColumn('themes', 'distribution_model')) {
                $table->dropIndex(['distribution_model']);
                $table->dropColumn('distribution_model');
            }
        });
    }
};
