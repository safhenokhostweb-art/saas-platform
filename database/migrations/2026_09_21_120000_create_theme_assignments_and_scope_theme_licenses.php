<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theme_licenses', function (Blueprint $table) {
            $table->foreignId('licensed_release_id')->nullable()->after('installed_release_id')
                ->constrained('theme_releases')->restrictOnDelete();
            $table->index(['site_id', 'theme_id', 'licensed_release_id'], 'theme_licenses_scope_index');
        });

        // Preserve known historical lineage without guessing: an existing
        // installed release is also the last release demonstrably granted.
        DB::table('theme_licenses')
            ->whereNull('licensed_release_id')
            ->whereNotNull('installed_release_id')
            ->update(['licensed_release_id' => DB::raw('installed_release_id')]);

        Schema::create('theme_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('theme_id')->constrained()->restrictOnDelete();
            $table->foreignId('theme_release_id')->constrained('theme_releases')->restrictOnDelete();
            $table->foreignId('platform_license_id')->nullable()->constrained('platform_licenses')->nullOnDelete();
            $table->foreignId('theme_license_id')->nullable()->constrained('theme_licenses')->nullOnDelete();
            $table->string('domain', 255);
            $table->string('source', 32);
            $table->string('status', 32)->default('assigned');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // The same release is idempotent; a newer release receives a
            // distinct immutable row so authorization lineage is retained.
            $table->unique(['site_id', 'theme_id', 'theme_release_id'], 'theme_assignments_release_unique');
            $table->index(['site_id', 'theme_release_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_assignments');

        Schema::table('theme_licenses', function (Blueprint $table) {
            $table->dropIndex('theme_licenses_scope_index');
            $table->dropForeign(['licensed_release_id']);
            $table->dropColumn('licensed_release_id');
        });
    }
};
