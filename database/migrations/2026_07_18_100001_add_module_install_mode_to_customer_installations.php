<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 17-C — Part 4: adds MODE_MODULE_INSTALL to
 * customer_installations.install_mode, distinct from MODE_MODULE_UPDATE.
 *
 * Domain distinction only — both modes route through the exact same
 * CustomerInstallationOrchestrator::moduleUpdateStages() stage list (see
 * that method's docblock for why: ExtractModulePackageStage already
 * handles "no existing module directory" safely, and taking a full
 * database backup before a first-time module install is no less
 * appropriate than before an upgrade). Keeping this as its own enum value
 * — rather than reusing MODE_MODULE_UPDATE for a fresh install — gives
 * ModuleInstallationDispatcher a way to record intent (first install vs.
 * upgrade) for reporting/audit, without duplicating any pipeline logic.
 *
 * Appended at the end of the enum list, matching the precedent set by
 * 2026_07_10_100000_add_module_update_mode_to_customer_installations.php:
 * MySQL enums store each row as the value's index in the list, so appending
 * preserves every existing row's stored value.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE customer_installations MODIFY COLUMN install_mode ENUM('MODE_FRESH','MODE_RESTORE','MODE_CLONE','MODE_MOVE','MODE_REINSTALL','MODE_MODULE_UPDATE','MODE_MODULE_INSTALL') NOT NULL DEFAULT 'MODE_FRESH'");
    }

    public function down(): void
    {
        // Defensive, mirroring the MODE_MODULE_UPDATE precedent: reassign any
        // row using the new value before shrinking the enum. MODE_MODULE_INSTALL
        // rows are domain-equivalent to MODE_MODULE_UPDATE (same stage list),
        // so that is the safe fallback rather than MODE_REINSTALL.
        DB::statement("UPDATE customer_installations SET install_mode = 'MODE_MODULE_UPDATE' WHERE install_mode = 'MODE_MODULE_INSTALL'");
        DB::statement("ALTER TABLE customer_installations MODIFY COLUMN install_mode ENUM('MODE_FRESH','MODE_RESTORE','MODE_CLONE','MODE_MOVE','MODE_REINSTALL','MODE_MODULE_UPDATE') NOT NULL DEFAULT 'MODE_FRESH'");
    }
};
