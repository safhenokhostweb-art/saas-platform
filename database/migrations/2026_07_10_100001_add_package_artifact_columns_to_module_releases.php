<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13-G — adds package-artifact reference columns to module_releases.
 *
 * Per the Phase 13-D finding: module_releases has never had anywhere to
 * record a built package's identity (no builder exists yet either — that's
 * a later phase). All four columns are nullable with no default, so every
 * existing row (all of them, since nothing has ever populated a package)
 * simply gets NULL. No index is added: nothing in the codebase queries by
 * these columns yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('module_releases', function (Blueprint $table) {
            $table->string('package_sha256', 64)->nullable()->after('is_active');
            $table->string('package_path', 500)->nullable()->after('package_sha256');
            $table->string('package_key', 500)->nullable()->after('package_path');
            $table->unsignedBigInteger('package_size_bytes')->nullable()->after('package_key');
        });
    }

    public function down(): void
    {
        Schema::table('module_releases', function (Blueprint $table) {
            $table->dropColumn([
                'package_sha256',
                'package_path',
                'package_key',
                'package_size_bytes',
            ]);
        });
    }
};
