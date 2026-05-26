<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Queue;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Services\Queue\QueueRuntimeCompatibilityService;

class QueueValidateRuntimeCommand extends Command
{
    protected $signature = 'queue:validate-runtime
                            {--json           : Output as JSON}
                            {--fail-on-warning: Exit 1 if any check fails}';

    protected $description = 'Validate queue runtime compatibility with the current release (Phase 8.2)';

    public function handle(QueueRuntimeCompatibilityService $service): int
    {
        $result = $service->validate();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result['passed'] ? 0 : ($this->option('fail-on-warning') ? 1 : 0);
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>QUEUE RUNTIME COMPATIBILITY — PHASE 8.2</>');
        $this->line('  ─────────────────────────────────────────────');
        $this->newLine();

        foreach ($result['checks'] as $check) {
            $icon = $check['passed'] ? '<fg=green>PASS</>' : '<fg=yellow>WARN</>';
            $name = str_pad($check['check'], 26);
            $this->line("  [{$icon}] {$name} {$check['detail']}");
        }

        $this->newLine();
        $status = $result['passed'] ? '<fg=green>✓ All checks passed</>' : '<fg=yellow>⚠  Review above</>';
        $this->line("  {$status} ({$result['score']})");
        $this->newLine();

        return (!$result['passed'] && $this->option('fail-on-warning')) ? 1 : 0;
    }
}
