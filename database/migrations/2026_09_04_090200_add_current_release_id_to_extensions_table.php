<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3G-B-1 — adds the "current version is a pointer only" mechanic (per
 * COMMERCIAL_PRODUCT_ARCHITECTURE_CONTRACT.md §5), mirroring
 * themes.current_release_id exactly: extensions.current_release_id points at
 * the extension_releases row that is authoritative right now. Deliberately
 * excluded from Extension::$fillable so it can only ever be moved by
 * ExtensionReleaseService, never by generic mass-assignment.
 *
 * A separate migration (not inlined into create_extensions_table) because
 * extension_releases must exist first for the FK constraint — same ordering
 * theme_releases/themes.current_release_id already used.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            $table->foreignId('current_release_id')->nullable()->after('description')
                ->constrained('extension_releases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('extensions', function (Blueprint $table) {
            $table->dropForeign(['current_release_id']);
            $table->dropColumn('current_release_id');
        });
    }
};
