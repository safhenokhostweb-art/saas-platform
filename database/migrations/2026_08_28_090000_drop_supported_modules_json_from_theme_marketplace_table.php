<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1j — retires theme_marketplace.supported_modules_json.
 *
 * Verified dead before writing this migration (search across saas-platform
 * and customer-site, app code, tests, and docs): the only two writers were
 * MarketplaceThemeSeedCommand and MalltinaThemeSeedCommand (both stopped
 * writing it in this same phase); ThemeMarketplaceApiController::formatTheme()
 * never included it in the public API response; no view, controller, or test
 * reads it. The data it duplicated already lives in
 * `Theme.settings['supported_modules']` and, once a theme is released, in
 * `theme_release_dependencies` (Phase 1e) — nothing unique is lost.
 *
 * `down()` restores the column (nullable, no data) rather than attempting to
 * reconstruct its former contents, matching how it always behaved for any
 * theme created after this migration — a schema rollback, not a data
 * restore.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('theme_marketplace', function (Blueprint $table) {
            $table->dropColumn('supported_modules_json');
        });
    }

    public function down(): void
    {
        Schema::table('theme_marketplace', function (Blueprint $table) {
            $table->string('supported_modules_json', 500)->nullable()->after('category_tags');
        });
    }
};
