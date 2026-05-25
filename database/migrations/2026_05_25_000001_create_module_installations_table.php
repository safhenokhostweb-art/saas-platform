<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 6.98 — TASK 7: Module Installation History Table
 *
 * Tracks every install, update, and rollback event for all modules
 * across all environments. Used by ModuleUpdateOrchestrator for
 * audit trails, rollback safety, and update eligibility checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_installations', function (Blueprint $table) {
            $table->id();

            // Module identity
            $table->string('module', 64)->index();
            $table->string('version', 32);
            $table->string('previous_version', 32)->nullable();
            $table->string('schema_version', 16)->default('1');

            // Timestamps
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();

            // Status lifecycle
            $table->enum('status', [
                'installed',    // Fresh install
                'updated',      // Successfully updated
                'failed',       // Update or install failed
                'rolled_back',  // Rolled back to previous version
            ])->default('installed')->index();

            // Environment & source
            $table->string('environment', 32)->default('production')
                ->comment('laravel environment: local, staging, production');

            $table->enum('source_type', [
                'symlink',  // Local dev path repository
                'satis',    // Private Satis composer repository
                'vcs',      // Direct VCS repository
                'archive',  // Zip/tar archive
            ])->default('symlink');

            // Integrity & metadata
            $table->string('checksum', 64)->nullable()
                ->comment('SHA-256 of module composer.json + module.json at install time');

            $table->json('release_manifest')->nullable()
                ->comment('Full release manifest snapshot at install time');

            $table->json('meta')->nullable()
                ->comment('Additional context: PHP version, Laravel version, operator, etc.');

            // Indexes for common queries
            $table->index(['module', 'status']);
            $table->index(['module', 'installed_at']);
            $table->index('environment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_installations');
    }
};
