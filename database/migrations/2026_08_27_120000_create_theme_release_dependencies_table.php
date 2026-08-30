<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1e — structured Theme Release dependency ledger.
 *
 * Generalizes ModuleDeploymentDependencyResolver's module-to-module pattern
 * (and the reserved-but-unpopulated theme_releases.dependencies JSON column
 * from Phase 1a) into a queryable, open-ended table per
 * THEME_PRODUCTIZATION_ARCHITECTURE.md Phase 2: one row per
 * {release, dependency_type, dependency_identifier}.
 *
 * `dependency_type` is deliberately a plain string, not a DB enum: today's
 * known values (module, motion_asset, motion_pack, icon_asset, icon_pack,
 * block_pack, design_system) are validated at the application layer only
 * (ThemeReleaseService), so a future product class is one more accepted
 * string, never a migration.
 *
 * Rows are immutable once created (ThemeReleaseDependency::booted()) — the
 * unique constraint below is defense-in-depth against a duplicate row
 * existing at all, on top of ThemeReleaseService's own pre-write check.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('theme_release_dependencies', function (Blueprint $table) {
            $table->id();
            // cascadeOnDelete: ThemeRelease rows are never deleted through
            // Eloquent (ThemeRelease::booted() blocks it) — this only
            // matters for the same documented bypass boundary (raw/bulk
            // writes) ThemeRelease's own guard already accepts, so a
            // dependency row can never outlive the release it describes.
            $table->foreignId('theme_release_id')->constrained()->cascadeOnDelete();
            $table->string('dependency_type', 40);
            $table->string('dependency_identifier', 191);
            $table->string('version_constraint', 64)->nullable();
            $table->boolean('is_required')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['theme_release_id', 'dependency_type', 'dependency_identifier'],
                'theme_release_dependency_unique'
            );
            $table->index(['dependency_type', 'dependency_identifier'], 'theme_release_dependency_type_identifier_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_release_dependencies');
    }
};
