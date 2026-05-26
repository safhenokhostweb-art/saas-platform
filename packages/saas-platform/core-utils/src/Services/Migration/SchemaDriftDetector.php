<?php

namespace SaaSPlatform\CoreUtils\Services\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detects drift between the recorded migration state and the actual DB schema.
 * Useful for identifying manual schema changes, partial deploys, or out-of-band modifications.
 */
class SchemaDriftDetector
{
    public function detect(): array
    {
        return [
            'pending_migrations'  => $this->findPendingMigrations(),
            'orphaned_migrations' => $this->findOrphanedMigrations(),
            'batch_gaps'          => $this->findBatchGaps(),
            'drifted_at'          => now()->toIso8601String(),
        ];
    }

    public function hasDrift(): bool
    {
        $result = $this->detect();
        return !empty($result['pending_migrations'])
            || !empty($result['orphaned_migrations'])
            || !empty($result['batch_gaps']);
    }

    private function findPendingMigrations(): array
    {
        $ran   = DB::table('migrations')->pluck('migration')->toArray();
        $files = $this->getAllMigrationFiles();
        return array_values(array_diff($files, $ran));
    }

    private function findOrphanedMigrations(): array
    {
        $ran   = DB::table('migrations')->pluck('migration')->toArray();
        $files = $this->getAllMigrationFiles();
        return array_values(array_diff($ran, $files));
    }

    private function findBatchGaps(): array
    {
        try {
            $batches = DB::table('migrations')
                ->select('batch')
                ->distinct()
                ->orderBy('batch')
                ->pluck('batch')
                ->toArray();

            if (empty($batches)) {
                return [];
            }

            $expected = range(min($batches), max($batches));
            $gaps     = array_diff($expected, $batches);

            return array_values($gaps);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getAllMigrationFiles(): array
    {
        $paths = [database_path('migrations')];

        // Also check package migration paths
        $packageBase = base_path('packages/saas-platform/modules');
        if (is_dir($packageBase)) {
            foreach (scandir($packageBase) as $module) {
                foreach (['Database/Migrations', 'database/migrations'] as $rel) {
                    $dir = "{$packageBase}/{$module}/{$rel}";
                    if (is_dir($dir)) {
                        $paths[] = $dir;
                    }
                }
            }
        }

        $files = [];
        foreach ($paths as $path) {
            foreach (glob($path . '/*.php') ?: [] as $file) {
                $files[] = pathinfo($file, PATHINFO_FILENAME);
            }
        }

        return $files;
    }
}
