<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RC-01 — Duplicate AI draft protection.
 *
 * MySQL/MariaDB path:
 *   — Cleans pre-existing duplicates before adding the constraint.
 *   — Adds UNIQUE INDEX (auto_generated, module_slug, site_id, title(191)).
 *     title(191): utf8mb4 @ 4 bytes/char → 764 bytes, within MySQL 5.7
 *     single-column key-prefix limit of 767 bytes.
 *
 * SQLite path (Orchestra Testbench / CI):
 *   — No duplicate cleanup needed (RefreshDatabase always starts fresh).
 *   — Column-prefix indexes are not supported; full title column is used.
 *   — Uses CREATE UNIQUE INDEX syntax (ALTER TABLE ADD INDEX is unsupported).
 *
 * Both paths add the same supporting lookup index so the lockForUpdate()
 * re-check in EducationDraftPersistenceService::persist() runs an index
 * scan rather than a full table scan.
 *
 * Null site_id: MySQL allows multiple NULL values in a unique index, so
 * this constraint does NOT protect platform-level (site_id IS NULL) rows.
 * That case is handled by the lockForUpdate() re-check inside persist().
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // Supporting lookup index — identical syntax on all supported drivers.
        Schema::table('edu_articles', function (Blueprint $table) {
            $table->index(
                ['auto_generated', 'site_id', 'module_slug'],
                'edu_articles_ai_draft_lookup'
            );
        });

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // Remove pre-existing duplicate auto-generated drafts so the unique
            // index can be created. Keeps the row with the highest id per group.
            DB::statement('
                DELETE a FROM edu_articles a
                INNER JOIN edu_articles b
                    ON  b.auto_generated   = a.auto_generated
                    AND b.module_slug      = a.module_slug
                    AND (b.site_id = a.site_id OR (b.site_id IS NULL AND a.site_id IS NULL))
                    AND LEFT(b.title, 191) = LEFT(a.title, 191)
                    AND b.id > a.id
            ');

            // Column-prefix unique index — raw DDL because Laravel Schema Builder
            // does not support per-column prefix lengths.
            DB::statement('
                ALTER TABLE edu_articles
                ADD UNIQUE INDEX edu_articles_ai_draft_unique
                    (auto_generated, module_slug, site_id, title(191))
            ');
        } else {
            // SQLite (Testbench / CI): no prefix index support and no
            // ALTER TABLE ADD INDEX — use CREATE UNIQUE INDEX on full column.
            DB::statement('
                CREATE UNIQUE INDEX edu_articles_ai_draft_unique
                ON edu_articles (auto_generated, module_slug, site_id, title)
            ');
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE edu_articles DROP INDEX edu_articles_ai_draft_unique');
        } else {
            DB::statement('DROP INDEX IF EXISTS edu_articles_ai_draft_unique');
        }

        Schema::table('edu_articles', function (Blueprint $table) {
            $table->dropIndex('edu_articles_ai_draft_lookup');
        });
    }
};
