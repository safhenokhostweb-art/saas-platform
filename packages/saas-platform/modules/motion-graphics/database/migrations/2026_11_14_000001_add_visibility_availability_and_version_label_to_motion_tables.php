<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-086: granular motion licensing, admin availability toggle, version labels.
 *
 * - `motion_graphics.visibility`: `public` (every entitled site may receive
 *   it) or `private` (only a site holding an explicit grant, see the host's
 *   motion_site_asset_grants). Existing rows become `public`, which is
 *   exactly how they behave today.
 * - `motion_graphics.runtime_enabled`: the admin kill switch. A disabled
 *   motion is dropped from every runtime manifest on the next fetch; nothing
 *   else about it changes (versions, bindings and grants stay intact), so
 *   turning it back on restores it as it was.
 * - `motion_graphic_versions.version_label`: the human release identifier of
 *   one version (`v2`, `1.2.0`). Nullable: rows published before this column
 *   existed are not rewritten (version rows are immutable), readers fall back
 *   to the library directory recorded in preview_metadata.
 *
 * Additive only: three nullable/defaulted columns, no data rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('motion_graphics', function (Blueprint $table): void {
            $table->string('visibility', 16)->default('public')->after('owner_site_identifier');
            $table->boolean('runtime_enabled')->default(true)->after('visibility');
            $table->timestamp('runtime_disabled_at')->nullable()->after('runtime_enabled');
            $table->string('runtime_disabled_by_actor_identifier', 191)->nullable()->after('runtime_disabled_at');
            $table->index(['visibility', 'runtime_enabled'], 'motion_graphics_visibility_enabled_index');
        });

        Schema::table('motion_graphic_versions', function (Blueprint $table): void {
            $table->string('version_label', 32)->nullable()->after('version');
        });
    }

    public function down(): void
    {
        Schema::table('motion_graphic_versions', function (Blueprint $table): void {
            $table->dropColumn('version_label');
        });

        Schema::table('motion_graphics', function (Blueprint $table): void {
            $table->dropIndex('motion_graphics_visibility_enabled_index');
            $table->dropColumn(['visibility', 'runtime_enabled', 'runtime_disabled_at', 'runtime_disabled_by_actor_identifier']);
        });
    }
};
