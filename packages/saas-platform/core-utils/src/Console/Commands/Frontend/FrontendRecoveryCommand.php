<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Frontend;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Frontend\FrontendRuntimeRecoveryService;

class FrontendRecoveryCommand extends Command
{
    protected $signature = 'frontend:recover
                            {--dry-run : Show what would be recovered without making changes}
                            {--json : Output as JSON}';

    protected $description = 'Diagnose and auto-recover canonical frontend runtime failures';

    public function handle(FrontendRuntimeRecoveryService $recovery): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->line('');
        $this->line('  <fg=blue;options=bold>Phase 10.0 — Frontend Runtime Recovery</>');
        if ($dryRun) $this->line('  <fg=yellow>(dry-run mode — no changes will be made)</>');
        $this->line('  ' . str_repeat('─', 48));

        $result = $recovery->diagnoseAndRecover($dryRun);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['healthy'] ? self::SUCCESS : self::FAILURE;
        }

        $this->line('');
        $this->info('  Diagnostics:');
        foreach ($result['diagnostics'] as $key => $diag) {
            $icon  = $diag['status'] === 'ok' ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $this->line("  {$icon}  {$diag['message']}");
        }

        if (! empty($result['recovered'])) {
            $this->line('');
            $this->info('  Recovered:');
            foreach ($result['recovered'] as $key => $action) {
                $this->line("  <fg=green>↺</>  {$action}");
            }
        }

        if (! empty($result['unrecoverable'])) {
            $this->line('');
            $this->warn('  Manual action required:');
            foreach ($result['unrecoverable'] as $key => $reason) {
                $this->line("  <fg=red>!</>  {$reason}");
            }
        }

        $this->line('');
        $this->line('  ' . str_repeat('─', 48));
        if ($result['healthy']) {
            $this->line('  <fg=green;options=bold>Frontend runtime is healthy.</>');
        } else {
            $this->line('  <fg=red;options=bold>Frontend runtime has unrecoverable issues — see above.</>');
        }
        $this->line('');

        return $result['healthy'] ? self::SUCCESS : self::FAILURE;
    }
}
