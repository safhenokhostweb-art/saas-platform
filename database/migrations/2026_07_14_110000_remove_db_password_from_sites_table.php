<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13-O12e — final removal of sites.db_password (legacy plaintext),
 * per the Phase 13-O12d audit and the Phase 13-O12e design. Every site's
 * database credential now lives exclusively in db_password_encrypted
 * (CredentialVault), populated for all existing sites via
 * `php artisan sites:migrate-db-passwords` (Phase 13-O12c) and confirmed
 * via `php artisan sites:migrate-db-passwords --verify` reporting
 * remaining_plaintext_count = 0 before this migration is ever run.
 *
 * IMPORTANT: down() restores the *column* only, not the data. Once this
 * migration has run, any plaintext value that was in db_password is gone
 * permanently — there is nothing to roll back to. This migration must
 * never be run against a database where `--verify` has not already
 * reported remaining_plaintext_count = 0.
 */
return new class extends Migration {
    public function up(): void
    {
        // Unconditional safety guard — refuse to drop the column if even one
        // site still has a plaintext value, regardless of whether an
        // operator remembered to run `--verify` first.
        if (Schema::hasColumn('sites', 'db_password')) {
            $remaining = DB::table('sites')
                ->whereNotNull('db_password')
                ->where('db_password', '!=', '')
                ->count();

            if ($remaining > 0) {
                throw new \RuntimeException(
                    "Refusing to drop sites.db_password: {$remaining} site(s) still have a plaintext value. " .
                    'Run `php artisan sites:migrate-db-passwords` and confirm `--verify` reports ' .
                    'remaining_plaintext_count = 0 before retrying this migration.'
                );
            }
        }

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('db_password');
        });
    }

    public function down(): void
    {
        // Schema-only restore — the column comes back empty (nullable).
        // No plaintext data can or will be recovered by this rollback.
        Schema::table('sites', function (Blueprint $table) {
            $table->string('db_password')->nullable()->after('db_password_encrypted');
        });
    }
};
