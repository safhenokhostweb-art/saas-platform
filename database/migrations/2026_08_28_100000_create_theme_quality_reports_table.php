<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2a — Commercial Quality Gate audit trail.
 *
 * One row per gate evaluation (not per release — a theme can be evaluated,
 * fail, be fixed, and evaluated again before ever producing a release).
 * `theme_release_id` starts null and is filled in only if the evaluation
 * passed and a real ThemeRelease was actually created from it
 * (ThemeCommercialQualityGate::publishIfCompliant()) — this is what makes a
 * commercial release "traceable to the quality result that approved it"
 * without requiring theme_releases itself to carry gate-specific columns.
 *
 * Failed evaluations are kept, not discarded — a failure is exactly as much
 * an audit fact as a pass, and hiding them would defeat the point of an
 * audit trail.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('theme_quality_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theme_id')->constrained()->cascadeOnDelete();
            // restrictOnDelete would be wrong here: a report can exist for a
            // release that was later superseded/rolled back — the release
            // row itself is never deleted (ThemeRelease::booted() blocks
            // it), but nullOnDelete matches the FK's own semantics if that
            // ever changes.
            $table->foreignId('theme_release_id')->nullable()->constrained('theme_releases')->nullOnDelete();
            $table->string('version', 32);
            $table->string('status', 20); // passed | failed
            $table->json('checks'); // array of {code, category, status, severity, message, details}
            $table->timestamp('evaluated_at');
            $table->timestamps();

            $table->index(['theme_id', 'evaluated_at']);
            $table->index(['theme_release_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_quality_reports');
    }
};
