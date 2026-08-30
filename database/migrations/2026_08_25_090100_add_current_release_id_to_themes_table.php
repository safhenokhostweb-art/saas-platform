<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1a — adds the "current version is a pointer only" mechanic (per
 * COMMERCIAL_PRODUCT_ARCHITECTURE_CONTRACT.md §5): themes.current_release_id
 * points at the theme_releases row that is authoritative right now. It is
 * deliberately excluded from Theme::$fillable so it can only ever be moved
 * by ThemeReleaseService, never by generic mass-assignment.
 *
 * themes.version/status/published_at are untouched by this migration and keep
 * their existing legacy behavior — this phase does not migrate existing themes
 * or rewire the existing Canvas/ThemeBuilderService publish path.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->foreignId('current_release_id')->nullable()->after('version')
                ->constrained('theme_releases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->dropForeign(['current_release_id']);
            $table->dropColumn('current_release_id');
        });
    }
};
