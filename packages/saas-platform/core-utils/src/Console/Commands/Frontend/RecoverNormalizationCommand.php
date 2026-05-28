<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Frontend;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Frontend\FrontendNormalizationRecoveryService;

class RecoverNormalizationCommand extends Command
{
    protected $signature = 'frontend:recover-normalization
                            {--dry-run : Show what would be done without making changes}
                            {--json    : Output as JSON}';

    protected $description = 'Recover frontend normalization — detect and repair broken component mappings, missing token imports, and stale view cache';

    public function handle(FrontendNormalizationRecoveryService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $this->option('json')) {
            $this->line('');
            $this->line('<fg=cyan;options=bold>╔══════════════════════════════════════════════════════╗</>');
            $this->line('<fg=cyan;options=bold>║      Frontend Normalization Recovery v10.1          ║</>');
            $this->line('<fg=cyan;options=bold>╚══════════════════════════════════════════════════════╝</>');
            if ($dryRun) {
                $this->line('<fg=yellow>Mode: DRY-RUN — no changes will be made</>');
            }
            $this->line('');
        }

        $result = $service->recover($dryRun);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return empty($result['failures']) ? self::SUCCESS : self::FAILURE;
        }

        // Recovery actions
        $this->line('<options=bold>Recovery Actions:</>');
        foreach ($result['recovered'] as $entry) {
            $icon  = $entry['action'] ? '<fg=green>✓</>' : '<fg=gray>→</>';
            $this->line("  {$icon} [{$entry['key']}] {$entry['message']}");
        }

        // Failures
        if (! empty($result['failures'])) {
            $this->line('');
            $this->line('<fg=red;options=bold>── Failures ────────────────────────────────────────────</>');
            foreach ($result['failures'] as $f) {
                $this->line("  <fg=red>✗</> [{$f['key']}] {$f['message']}");
            }
        }

        $this->line('');
        if (empty($result['failures'])) {
            $this->line('<fg=green;options=bold>✓ Frontend normalization recovery completed successfully!</>');
        } else {
            $this->line('<fg=yellow;options=bold>⚠ Recovery completed with failures — manual intervention needed.</>');
        }
        $this->line('<options=bold>' . $result['summary'] . '</>');
        $this->line('');

        return empty($result['failures']) ? self::SUCCESS : self::FAILURE;
    }
}
