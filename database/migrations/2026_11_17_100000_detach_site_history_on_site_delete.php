<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a site failed with SQLSTATE 1451 (HTTP 500) whenever the site had
 * any licence or motion history: these five tables referenced `sites` with
 * ON DELETE RESTRICT.
 *
 * Owner decision 2026-11-17: a site is really deleted, and its history is
 * kept, detached. `site_id` becomes nullable with ON DELETE SET NULL, so:
 *  - module_entitlements (append-only licence ledger) keeps customer_id,
 *    module_slug and the invoice links;
 *  - motion_bespoke_commissions / _purchases keep the financial record;
 *  - motion_bespoke_assignments and motion_site_asset_packages keep their
 *    own append-only children (assignment events, package snapshots),
 *    which cascade from them and would be lost if they were deleted.
 * Nothing here can delete a row. Every other table that references `sites`
 * already uses CASCADE or SET NULL.
 *
 * Laravel 10 without doctrine/dbal cannot `->change()` a column, hence the
 * raw MySQL statements. Other drivers are left as they are.
 */
return new class extends Migration
{
    private const TABLES = [
        'module_entitlements',
        'motion_bespoke_commissions',
        'motion_bespoke_assignments',
        'motion_bespoke_purchases',
        'motion_site_asset_packages',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $this->rebuildSiteForeignKey($table, nullable: true, onDelete: 'SET NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->whereNull('site_id')->exists()) {
                throw new RuntimeException(
                    "{$table} holds history of deleted sites (site_id NULL); restoring NOT NULL / RESTRICT would require deleting it."
                );
            }
        }

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $this->rebuildSiteForeignKey($table, nullable: false, onDelete: 'RESTRICT');
        }
    }

    private function rebuildSiteForeignKey(string $table, bool $nullable, string $onDelete): void
    {
        $constraint = "{$table}_site_id_foreign";

        $exists = DB::selectOne(
            'SELECT 1 AS present FROM information_schema.REFERENTIAL_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $constraint]
        );
        if ($exists) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        }

        $null = $nullable ? 'NULL' : 'NOT NULL';
        DB::statement("ALTER TABLE `{$table}` MODIFY `site_id` BIGINT UNSIGNED {$null}");
        DB::statement(
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE {$onDelete}"
        );
    }
};
