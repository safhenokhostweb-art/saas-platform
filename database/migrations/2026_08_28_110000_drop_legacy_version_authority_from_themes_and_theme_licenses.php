<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2b — retires the legacy Theme.version int / ThemeLicense.
 * installed_theme_version commercial-version-comparison pair.
 *
 * ADR-021 kept these as a transitional fallback specifically because
 * ThemeLicense::isOutdated()/Theme::outdatedLicensesCount() had no
 * ledger-based equivalent yet, and ADR-022 kept them further because there
 * might be an active tenant fleet running an un-upgraded customer-site
 * still reading the API's legacy `version`/`is_outdated`/
 * `installed_theme_version` fields. The business owner has now confirmed
 * (Phase 2b) there are no active customers and no production Theme fleet —
 * that compatibility reason no longer exists. ThemeRelease.version +
 * ThemeLicense.installed_release_id/isOutdatedByLedger() are the sole
 * remaining commercial version authority; see the ADR amendment in
 * DECISIONS.md for the full reasoning.
 *
 * No data is preserved by this migration: both columns are dropped as-is.
 * This is deliberate, not an oversight — there is no non-active-customer
 * data in either column worth migrating forward, and fabricating a mapping
 * from the legacy int to a semver value would itself violate the
 * "never fabricate version data" rule this whole architecture is built on.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->dropColumn('version');
        });

        Schema::table('theme_licenses', function (Blueprint $table) {
            $table->dropColumn('installed_theme_version');
        });
    }

    public function down(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('published_at');
        });

        Schema::table('theme_licenses', function (Blueprint $table) {
            $table->smallInteger('installed_theme_version')->unsigned()->nullable()->after('is_permanent');
        });
    }
};
