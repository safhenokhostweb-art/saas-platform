<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Forms\Services\FormsBuilderRuntimeRecoveryService;

/**
 * Phase 10.2.6 — Builder Runtime Recovery Command
 */
class RecoverBuilderRuntimeCommand extends Command
{
    protected $signature = 'forms:recover-builder-runtime
                            {--dry-run : Show what would be recovered without making changes}
                            {--json : Output results as JSON}';

    protected $description = 'Phase 10.2.6 — Auto-recover Forms Builder runtime failures (routes, view, Alpine, CSS)';

    public function handle(FormsBuilderRuntimeRecoveryService $service): int
    {
        $dryRun     = (bool) $this->option('dry-run');
        $jsonOutput = (bool) $this->option('json');

        if ($dryRun) {
            $this->line('  <fg=yellow>DRY RUN — no changes will be made</>');
        }

        $result = $service->recover($dryRun);

        if ($jsonOutput) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['success'] ? Command::SUCCESS : Command::FAILURE;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Forms Builder Runtime Recovery — Phase 10.2.6</>');
        $this->newLine();

        foreach ($result['report'] as $key => $item) {
            $icon = match ($item['status']) {
                'healthy'   => '<fg=green>✓</>',
                'recovered' => '<fg=cyan>↻</>',
                'skipped'   => '<fg=yellow>–</>',
                'failed'    => '<fg=red>✗</>',
                default     => '·',
            };

            $label = str_pad(str_replace('_', ' ', $key), 36);
            $msg   = "<fg=gray>{$item['message']}</>";
            $this->line("  {$icon}  {$label}  {$msg}");
        }

        $this->newLine();
        $this->line("  Healthy: {$result['healthy']}  |  Recovered: {$result['recovered']}  |  Failed: {$result['failed']}  |  Skipped: {$result['skipped']}");

        if (! $result['success']) {
            $this->newLine();
            $this->line('  <fg=red;options=bold>Some issues require manual intervention (see above)</>');
            $this->line('  <fg=gray>Review docs/phase-10.2.6/forms-builder-runtime-trace.md for structural fixes</>');
            $this->newLine();
            return Command::FAILURE;
        }

        $this->newLine();
        $this->line('  <fg=green;options=bold>✓  Builder runtime recovery complete</>');
        $this->newLine();
        return Command::SUCCESS;
    }
}
