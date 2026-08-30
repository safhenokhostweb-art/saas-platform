<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1d — ThemeLicense version transition (ADR-021 §3/§6).
 *
 * Adds the ledger-aware installed-version reference without touching the
 * legacy `installed_theme_version` int column or any existing row's data:
 * every existing ThemeLicense gets `installed_release_id = NULL` (the
 * column default), which is the honest answer — this migration does not
 * know, and must not guess, which ThemeRelease (if any) an existing
 * license's legacy integer actually corresponds to. Reinterpreting that
 * integer as a fabricated release reference would be exactly the kind of
 * invented history THEME_PRODUCTIZATION_ARCHITECTURE.md Phase 3 forbids for
 * the release ledger itself; the same rule applies here.
 *
 * `ThemeLicense::grant()` starts populating this column for every license
 * granted *after* this migration runs (see ThemeLicense.php). Existing rows
 * remain on the legacy `installed_theme_version` comparison
 * (`ThemeLicense::isOutdated()`) until a future, explicit migration step
 * backfills or re-grants them — not performed here.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('theme_licenses', function (Blueprint $table) {
            $table->foreignId('installed_release_id')->nullable()->after('installed_theme_version')
                ->constrained('theme_releases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('theme_licenses', function (Blueprint $table) {
            $table->dropForeign(['installed_release_id']);
            $table->dropColumn('installed_release_id');
        });
    }
};
