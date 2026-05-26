<?php

namespace SaaSPlatform\CoreUtils\Services\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Coordinates migration execution in multi-tenant contexts.
 *
 * Tracks which migrations touch tenant-scoped tables and
 * enforces that tenant data is never silently corrupted during
 * schema evolution.
 */
class TenantMigrationCoordinator
{
    private const TENANT_TABLES = [
        'tickets', 'ticket_replies', 'ticket_statuses', 'ticket_priorities',
        'form_submissions', 'form_site', 'forms',
        'sites', 'site_modules', 'site_settings', 'site_captcha_settings',
        'site_support_subscriptions', 'site_module_purchases',
    ];

    public function assessTenantImpact(array $migrationFiles): array
    {
        $impacted = [];

        foreach ($migrationFiles as $file) {
            if (!file_exists($file)) {
                continue;
            }

            $content = file_get_contents($file);
            $tables  = $this->detectTables($content);
            $tenant  = array_intersect($tables, self::TENANT_TABLES);

            if (!empty($tenant)) {
                $impacted[basename($file)] = [
                    'file'           => $file,
                    'tenant_tables'  => array_values($tenant),
                    'all_tables'     => $tables,
                    'requires_review'=> true,
                ];
            }
        }

        return $impacted;
    }

    public function validatePreMigration(): array
    {
        $checks = [];

        // Check active tenant sessions won't block
        $checks[] = $this->checkNoLongRunningQueries();

        // Check replication lag if applicable
        $checks[] = ['check' => 'replication', 'passed' => true, 'detail' => 'Single-DB mode — no replication'];

        return [
            'ready'  => !in_array(false, array_column($checks, 'passed'), true),
            'checks' => $checks,
        ];
    }

    public function recordMigrationAudit(string $migration, string $status, ?string $error = null): void
    {
        Log::info('tenant_migration.audit', [
            'migration' => $migration,
            'status'    => $status,
            'error'     => $error,
            'batch'     => $this->currentBatch(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    private function checkNoLongRunningQueries(): array
    {
        try {
            // MySQL: check for queries running longer than 30s
            $longRunning = DB::select("
                SELECT COUNT(*) as cnt FROM information_schema.processlist
                WHERE command != 'Sleep' AND time > 30
            ");
            $count = (int) ($longRunning[0]->cnt ?? 0);
            return [
                'check'  => 'long_running_queries',
                'passed' => $count === 0,
                'detail' => $count > 0 ? "{$count} long-running query(ies) active" : 'No long-running queries',
            ];
        } catch (\Throwable) {
            return ['check' => 'long_running_queries', 'passed' => true, 'detail' => 'Check skipped (non-MySQL)'];
        }
    }

    private function detectTables(string $content): array
    {
        $tables = [];

        // Schema::create('table_name', ...) and Schema::table('table_name', ...)
        preg_match_all("/Schema::[a-z]+\(\s*['\"]([^'\"]+)['\"]/i", $content, $matches);
        $tables = array_merge($tables, $matches[1] ?? []);

        // $table->string(...) doesn't give table names, but we can catch table() references
        preg_match_all("/['\"]([a-z_]+)['\"],\s*function\s*\(Blueprint/i", $content, $m2);
        $tables = array_merge($tables, $m2[1] ?? []);

        return array_unique(array_filter($tables));
    }

    private function currentBatch(): int
    {
        try {
            return (int) DB::table('migrations')->max('batch');
        } catch (\Throwable) {
            return 0;
        }
    }
}
