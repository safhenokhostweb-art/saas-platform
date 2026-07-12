<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13-O9c — closes a gap left by the O9a migration: nothing stopped
 * two InstallationCredential rows of the same type existing for one
 * installation (e.g. a retried GENERATE_ENVIRONMENT stage calling
 * InstallationCredential::create() twice). Replaces the plain
 * (installation_id, type) index with a unique one.
 */
return new class extends Migration {
    public function up(): void
    {
        // The existing plain index on (installation_id, type) is what
        // currently satisfies MySQL's requirement that a foreign key column
        // be indexed, so it can't be dropped first — add the unique index
        // (which also satisfies that requirement) before dropping the old one.
        Schema::table('installation_credentials', function (Blueprint $table) {
            $table->unique(['installation_id', 'type']);
        });

        Schema::table('installation_credentials', function (Blueprint $table) {
            $table->dropIndex(['installation_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('installation_credentials', function (Blueprint $table) {
            $table->index(['installation_id', 'type']);
        });

        Schema::table('installation_credentials', function (Blueprint $table) {
            $table->dropUnique(['installation_id', 'type']);
        });
    }
};
