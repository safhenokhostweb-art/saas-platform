<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1c hardening — theme_releases.published_at.
 *
 * `created_at` already exists via timestamps(), but it is Eloquent
 * bookkeeping, not commercial metadata — relying on it conflates "row
 * inserted" with "released", the same distinction module_releases already
 * makes with its own dedicated `distributed_at` column. `published_at` is
 * set once by ThemeReleaseService::release() at creation time and is frozen
 * by ThemeRelease::booted() alongside every other content column — it is not
 * updated by rollback()/restoreToDraft(), so it always reflects when this
 * specific release row first went live, never when it was last made current.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('theme_releases', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('theme_releases', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }
};
