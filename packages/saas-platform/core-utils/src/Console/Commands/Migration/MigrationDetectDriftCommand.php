<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Migration;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Services\Migration\SchemaDriftDetector;

class MigrationDetectDriftCommand extends Command
{
    protected $signature = 'migration:detect-drift
                            {--json            : Output as JSON}
                            {--fail-on-drift   : Exit 1 if drift detected}';

    protected $description = 'Detect schema drift between migration records and DB state (Phase 8.2)';

    public function handle(SchemaDriftDetector $detector): int
    {
        $result = $detector->detect();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $detector->hasDrift() && $this->option('fail-on-drift') ? 1 : 0;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>SCHEMA DRIFT DETECTOR — PHASE 8.2</>');
        $this->line('  ─────────────────────────────────────');
        $this->newLine();

        $pending  = $result['pending_migrations']  ?? [];
        $orphaned = $result['orphaned_migrations'] ?? [];
        $gaps     = $result['batch_gaps']          ?? [];

        if (empty($pending) && empty($orphaned) && empty($gaps)) {
            $this->line('  <fg=green>✓ No schema drift detected</> — migrations are consistent.');
            $this->newLine();
            return 0;
        }

        if (!empty($pending)) {
            $this->line("  <fg=yellow>⚠  Pending migrations (" . count($pending) . "):</>");
            foreach ($pending as $m) {
                $this->line("     - {$m}");
            }
            $this->newLine();
        }

        if (!empty($orphaned)) {
            $this->line("  <fg=red>✗  Orphaned migrations (" . count($orphaned) . ") — in DB but no file:</>");
            foreach ($orphaned as $m) {
                $this->line("     - {$m}");
            }
            $this->newLine();
        }

        if (!empty($gaps)) {
            $this->line("  <fg=yellow>⚠  Batch gaps found:</>");
            foreach ($gaps as $gap) {
                $this->line("     Missing batch: {$gap}");
            }
            $this->newLine();
        }

        if ($this->option('fail-on-drift')) {
            return 1;
        }

        return 0;
    }
}
