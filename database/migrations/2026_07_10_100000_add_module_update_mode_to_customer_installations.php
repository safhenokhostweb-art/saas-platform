<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 13-G — adds MODE_MODULE_UPDATE to customer_installations.install_mode.
 *
 * Appended at the end of the enum list, matching the precedent set by
 * 2026_06_30_400002_add_deleted_status_to_customer_installations.php:
 * MySQL enums store each row as the value's index in the list, so appending
 * preserves every existing row's stored value. Inserting mid-list or
 * reordering would silently reinterpret existing data.
 *
 * Foundation only — no pipeline in this codebase writes this value yet
 * (that lands in a later phase). This migration only makes the value legal.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE customer_installations MODIFY COLUMN install_mode ENUM('MODE_FRESH','MODE_RESTORE','MODE_CLONE','MODE_MOVE','MODE_REINSTALL','MODE_MODULE_UPDATE') NOT NULL DEFAULT 'MODE_FRESH'");
    }

    public function down(): void
    {
        // Defensive, mirroring the deleted-status precedent: reassign any row
        // using the new value before shrinking the enum. No writer of
        // MODE_MODULE_UPDATE exists yet as of this migration, so this is a
        // safety net, not an expected code path.
        DB::statement("UPDATE customer_installations SET install_mode = 'MODE_REINSTALL' WHERE install_mode = 'MODE_MODULE_UPDATE'");
        DB::statement("ALTER TABLE customer_installations MODIFY COLUMN install_mode ENUM('MODE_FRESH','MODE_RESTORE','MODE_CLONE','MODE_MOVE','MODE_REINSTALL') NOT NULL DEFAULT 'MODE_FRESH'");
    }
};
