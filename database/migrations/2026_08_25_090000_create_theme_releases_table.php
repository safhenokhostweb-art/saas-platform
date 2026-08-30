<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1a — Theme Product Release Ledger Foundation.
 *
 * theme_releases is the append-only commercial release ledger for Theme Products,
 * distinct from theme_revisions (an existing, unrelated draft/checkpoint tool used
 * while authoring — see ThemeBuilderService::saveRevision()). A row here represents
 * an immutable, versioned, customer-facing release; theme_revisions rows represent
 * ad hoc "undo points" an editor can create at any time while drafting.
 *
 * Content columns (version, snapshot, changelog, release_notes, compatibility,
 * dependencies, published_by, theme_id) are enforced immutable at the model layer
 * (ThemeRelease::booted()) — only `status` may transition after creation.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('theme_releases', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete, not cascadeOnDelete: Theme has no SoftDeletes
            // trait, so ThemeBuilderController::destroy() performs a real hard
            // delete. The release ledger must survive that — deleting a Theme
            // Product's master row must never silently erase its commercial
            // release history. A theme with zero releases can still be deleted
            // freely; one with real releases must be handled deliberately.
            $table->foreignId('theme_id')->constrained()->restrictOnDelete();
            $table->string('version', 32); // semver, e.g. "1.0.0"
            $table->text('changelog');
            $table->text('release_notes')->nullable();
            $table->string('status', 20)->default('published'); // published, superseded, deprecated
            // Reserved for Phase 1b: structured platform/compatibility constraints.
            // Not populated for existing themes by this migration.
            $table->json('compatibility')->nullable();
            // Reserved for Phase 1b: [{type: module|motion_asset|icon_pack|block_pack, ref, version_constraint, is_required}].
            // Intentionally left empty here — no dependency extraction is performed in Phase 1a.
            $table->json('dependencies')->nullable();
            // Full frozen payload at release time: header_blocks, footer_blocks, pages, global_styles, settings.
            $table->json('snapshot');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['theme_id', 'version']);
            $table->index(['theme_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_releases');
    }
};
