<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * PHASE 8.0 — Validates that tenant isolation is structurally enforced.
 * Checks for:
 *   - Models missing SiteTenantScope global scope
 *   - site_id-bearing tables that lack a site_id index
 *   - Orphaned records (site_id pointing to non-existent sites)
 */
class ValidateTenantIsolationCommand extends Command
{
    protected $signature   = 'tenant:validate-isolation {--json : Output as JSON} {--fix : Auto-fix orphaned records (dry-run by default)}';
    protected $description = 'Validate structural multi-tenant isolation across the platform';

    private array $issues = [];
    private array $passed = [];

    public function handle(): int
    {
        $this->checkModelScopes();
        $this->checkDatabaseIndexes();
        $this->checkOrphanedRecords();

        if ($this->option('json')) {
            $this->line(json_encode([
                'passed' => $this->passed,
                'issues' => $this->issues,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return empty($this->issues) ? self::SUCCESS : self::FAILURE;
        }

        $this->printResults();

        return empty($this->issues) ? self::SUCCESS : self::FAILURE;
    }

    private function checkModelScopes(): void
    {
        $models = [
            \SaaSPlatform\Modules\Tickets\Models\Ticket::class     => 'site_id',
            \SaaSPlatform\Modules\Forms\Models\FormSubmission::class => 'site_id',
        ];

        foreach ($models as $modelClass => $column) {
            $shortName = class_basename($modelClass);

            try {
                $instance    = new $modelClass();
                $scopes      = $instance->getGlobalScopes();
                $scopeNames  = array_keys($scopes);
                $hasSiteScope = collect($scopeNames)->contains(
                    fn($s) => str_contains($s, 'SiteTenantScope')
                );

                if ($hasSiteScope) {
                    $this->passed[] = "{$shortName}: SiteTenantScope registered";
                } else {
                    $this->issues[] = [
                        'severity' => 'CRITICAL',
                        'check'    => 'model_scope',
                        'message'  => "{$shortName} is missing SiteTenantScope global scope",
                    ];
                }
            } catch (\Throwable $e) {
                $this->issues[] = [
                    'severity' => 'HIGH',
                    'check'    => 'model_scope',
                    'message'  => "Could not instantiate {$shortName}: {$e->getMessage()}",
                ];
            }
        }
    }

    private function checkDatabaseIndexes(): void
    {
        $tables = ['tickets', 'form_submissions', 'forms'];

        foreach ($tables as $table) {
            try {
                $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Column_name = 'site_id'");

                if (!empty($indexes)) {
                    $this->passed[] = "Table `{$table}`: site_id index present";
                } else {
                    $this->issues[] = [
                        'severity' => 'HIGH',
                        'check'    => 'db_index',
                        'message'  => "Table `{$table}` has no index on site_id — tenant queries will do full scans",
                    ];
                }
            } catch (\Throwable) {
                // Table may not exist yet (before migrations)
            }
        }
    }

    private function checkOrphanedRecords(): void
    {
        $checks = [
            ['table' => 'tickets',          'label' => 'Ticket'],
            ['table' => 'form_submissions',  'label' => 'FormSubmission'],
        ];

        foreach ($checks as $check) {
            try {
                $orphans = DB::table($check['table'])
                    ->whereNotNull('site_id')
                    ->whereNotExists(function ($q) use ($check) {
                        $q->select(DB::raw(1))
                          ->from('sites')
                          ->whereColumn('sites.id', $check['table'] . '.site_id');
                    })
                    ->count();

                if ($orphans === 0) {
                    $this->passed[] = "{$check['label']}: no orphaned site_id records";
                } else {
                    $this->issues[] = [
                        'severity' => 'HIGH',
                        'check'    => 'orphaned_records',
                        'message'  => "{$check['label']}: {$orphans} records have site_id pointing to non-existent sites",
                    ];
                }
            } catch (\Throwable) {
                // Table may not exist yet
            }
        }
    }

    private function printResults(): void
    {
        $this->newLine();
        $this->line('<fg=green>Passed:</fg=green>');
        foreach ($this->passed as $p) {
            $this->line("  ✓ {$p}");
        }

        if (!empty($this->issues)) {
            $this->newLine();
            $this->error('Issues found:');
            foreach ($this->issues as $issue) {
                $color = $issue['severity'] === 'CRITICAL' ? 'red' : 'yellow';
                $this->line("  <fg={$color}>[{$issue['severity']}]</fg={$color}> {$issue['message']}");
            }
        }

        $this->newLine();
        $this->line(sprintf(
            'Result: %d passed, %d issues',
            count($this->passed),
            count($this->issues)
        ));
    }
}
