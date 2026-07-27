<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 17-H — Part 2: adds the Release Status Lifecycle
 * (draft → validated → built → published → deprecated) to module_releases.
 *
 * Additive only — `is_active` is untouched and keeps its existing meaning
 * (a rollback-safety flag; see docs/architecture/module-version-authority-adr.md).
 * `status` is a new, finer-grained axis: whether this release has cleared
 * the Marketplace publishing gate (Part 3/6), independent of whether it's
 * currently considered a valid rollback target.
 *
 * Default is 'published', not 'draft' — every row that exists before this
 * migration (and every future row created via App\Models\ModuleRelease
 * directly, bypassing ModuleReleaseProducerAdapter — e.g. every test in
 * this suite) represents a release that was already immediately usable
 * under the pre-17-H rules. Defaulting to 'draft' would silently make
 * every existing release and every existing test undeployable. Only
 * ModuleReleaseProducerAdapter's interactive `module:release` path
 * explicitly opts into starting at 'draft' — see its own change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_releases', function (Blueprint $table) {
            $table->string('status', 16)->default('published')->after('is_active');
        });

        // Backfill: a release that never got a package is not really
        // usable regardless of what it was defaulted to — treat it as
        // still draft. A release that was previously deactivated
        // (is_active=false) is treated as deprecated, matching the ADR's
        // existing "is_active allows a bad release to be deactivated"
        // semantics.
        DB::table('module_releases')->whereNull('package_sha256')->update(['status' => 'draft']);
        DB::table('module_releases')->where('is_active', false)->update(['status' => 'deprecated']);
    }

    public function down(): void
    {
        Schema::table('module_releases', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
