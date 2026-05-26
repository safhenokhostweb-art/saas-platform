<?php

namespace SaaSPlatform\Modules\Tickets\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Tickets\Services\TicketDataMigrationService;
use SaaSPlatform\Modules\Tickets\Services\TicketIntegrityValidationService;
use SaaSPlatform\Modules\Tickets\Services\TicketRollbackService;

/**
 * PHASE 7.2 — TASK 4
 *
 * php artisan tickets:migrate-data            # migrate support_tickets → tickets
 * php artisan tickets:migrate-data --dry-run  # preview without writing
 * php artisan tickets:migrate-data --validate # validate after migration
 * php artisan tickets:migrate-data --rollback # truncate canonical tables
 */
class MigrateTicketDataCommand extends Command
{
    protected $signature = 'tickets:migrate-data
                            {--dry-run  : Preview migration without writing}
                            {--validate : Run integrity validation after migration}
                            {--rollback : Truncate canonical tables (requires confirmation)}';

    protected $description = 'Migrate ticket data from legacy support_tickets to canonical tickets table (PHASE 7.2)';

    public function handle(
        TicketDataMigrationService       $migrationService,
        TicketIntegrityValidationService $validationService,
        TicketRollbackService            $rollbackService,
    ): int {
        if ($this->option('rollback')) {
            return $this->performRollback($rollbackService);
        }

        if ($this->option('dry-run')) {
            return $this->performDryRun($rollbackService);
        }

        return $this->performMigration($migrationService, $validationService);
    }

    private function performDryRun(TicketRollbackService $rollbackService): int
    {
        $this->info('DRY RUN — No data will be written');
        $preview = $rollbackService->dryRun();

        $this->table(
            ['Table', 'Row Count'],
            [
                ['support_tickets (legacy)',        $preview['legacy_tickets']],
                ['support_ticket_replies (legacy)', $preview['legacy_replies']],
                ['tickets (canonical)',             $preview['canonical_tickets']],
                ['ticket_replies (canonical)',      $preview['canonical_replies']],
            ]
        );

        $toMigrate = $preview['legacy_tickets'] - $preview['canonical_tickets'];
        $this->line("Tickets to migrate: <fg=yellow>{$toMigrate}</>");

        return 0;
    }

    private function performMigration(
        TicketDataMigrationService       $migrationService,
        TicketIntegrityValidationService $validationService,
    ): int {
        $this->info('Starting migration: support_tickets → tickets ...');

        $result = $migrationService->migrate();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Tickets migrated', $result['migrated_tickets']],
                ['Replies migrated', $result['migrated_replies']],
                ['Tickets skipped (already present)', $result['skipped']],
                ['Errors', count($result['errors'])],
            ]
        );

        if (!empty($result['errors'])) {
            $this->error('Migration errors:');
            foreach (array_slice($result['errors'], 0, 10) as $error) {
                $this->line("  · {$error}");
            }
        }

        if ($this->option('validate')) {
            $this->newLine();
            $this->info('Running integrity validation...');
            $validation = $validationService->validate();

            foreach ($validation['checks'] as $name => $check) {
                $icon = $check['passed'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
                $this->line("  [{$icon}] {$name}: {$check['message']}");
            }

            if (!$validation['passed']) {
                $this->error('Integrity validation FAILED — review above');
                return 1;
            }

            $this->info('Integrity validation PASSED');
        }

        $this->info('Migration complete.');

        return empty($result['errors']) ? 0 : 1;
    }

    private function performRollback(TicketRollbackService $rollbackService): int
    {
        if (!$rollbackService->isSafe()) {
            $this->error('Rollback is NOT safe: legacy support_tickets table appears empty. Aborting.');
            return 1;
        }

        $preview = $rollbackService->dryRun();
        $this->warn("This will DELETE {$preview['canonical_tickets']} tickets and {$preview['canonical_replies']} replies from canonical tables.");
        $this->warn('Legacy support_tickets table will NOT be touched.');

        if (!$this->confirm('Proceed with rollback?', false)) {
            $this->info('Rollback cancelled.');
            return 0;
        }

        $result = $rollbackService->rollback('CONFIRM_ROLLBACK');

        if (!$result['rolled_back']) {
            $this->error('Rollback failed: ' . $result['message']);
            return 1;
        }

        $this->info($result['message']);

        return 0;
    }
}
