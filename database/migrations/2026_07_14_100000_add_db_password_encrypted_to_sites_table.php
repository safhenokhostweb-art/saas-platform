<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13-O12a — step one of the approved two-stage Option C
 * (Phase 13-O11 design): adds encrypted storage for sites.db_password
 * without touching the existing plaintext column or any prior migration.
 *
 * Values are encrypted with CredentialVault (INSTALLER_MASTER_KEY), the
 * same mechanism already used for InstallationCredential/CustomerRemoteServer/
 * DnsProviderSetting/SshKey — never with APP_KEY.
 *
 * The old `db_password` column is deliberately left untouched here (no
 * backfill, no drop) — existing sites keep working via Site::getDecryptedDbPassword()'s
 * fallback until the UI + backfill step (a later phase) closes the gap.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->text('db_password_encrypted')->nullable()->after('db_password');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('db_password_encrypted');
        });
    }
};
