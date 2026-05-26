<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Migration;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Services\Migration\MigrationSafetyService;
use SaaSPlatform\CoreUtils\Services\Migration\TenantMigrationCoordinator;

class MigrationValidateSafetyCommand extends Command
{
    protected $signature = 'migration:validate-safety
                            {--force-production : Allow dangerous migrations (OVERRIDES safety gates)}
                            {--json             : Output as JSON}
                            {--fail-on-warning  : Exit 1 if any HIGH+ risk found}';

    protected $description = 'Validate pending migrations for production safety (Phase 8.2)';

    public function handle(
        MigrationSafetyService      $safety,
        TenantMigrationCoordinator  $coordinator
    ): int {
        $pending = $safety->analyzePendingMigrations();
        $preCheck = $coordinator->validatePreMigration();

        if ($this->option('json')) {
            $this->line(json_encode([
                'pending'   => $pending,
                'pre_check' => $preCheck,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return 0;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>MIGRATION SAFETY VALIDATOR — PHASE 8.2</>');
        $this->line('  ───────────────────────────────────────────');
        $this->newLine();

        if (empty($pending)) {
            $this->line('  <fg=green>✓ No pending migrations</> — database is up to date.');
            $this->newLine();
            return 0;
        }

        $hasBlocker = false;

        foreach ($pending as $name => $analysis) {
            $risk  = $analysis['risk_level'] ?? 'LOW';
            $color = match ($risk) {
                'CRITICAL' => 'red',
                'HIGH'     => 'red',
                'MEDIUM'   => 'yellow',
                default    => 'green',
            };

            $safeIcon = $analysis['safe'] ? '<fg=green>SAFE</>' : "<fg={$color}>{$risk}</>";
            $this->line("  [{$safeIcon}] {$name}");

            foreach ($analysis['findings'] ?? [] as $finding) {
                $this->line("         <fg=yellow>⚠</> {$finding['risk']}: {$finding['label']}");
            }

            if ($analysis['tenant_sensitive'] ?? false) {
                $this->line("         <fg=yellow>⚠</> Touches tenant-sensitive table(s)");
            }

            if ($analysis['irreversible'] ?? false) {
                $this->line("         <fg=red>✗</> Migration appears irreversible (empty down())");
            }

            if (in_array($risk, ['CRITICAL', 'HIGH'])) {
                $hasBlocker = true;
            }
        }

        $this->newLine();

        if (!$preCheck['ready']) {
            $this->warn('Pre-migration checks failed:');
            foreach ($preCheck['checks'] as $check) {
                if (!$check['passed']) {
                    $this->line("  ✗ {$check['check']}: {$check['detail']}");
                }
            }
        }

        if ($hasBlocker && !$this->option('force-production')) {
            $this->newLine();
            $this->error('Dangerous migrations detected. Add --force-production to override safety gates.');
            return $this->option('fail-on-warning') ? 1 : 0;
        }

        $this->line('  <fg=green>Safety validation passed.</>');
        $this->newLine();
        return 0;
    }
}
