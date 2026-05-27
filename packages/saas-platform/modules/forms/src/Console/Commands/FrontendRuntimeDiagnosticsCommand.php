<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\Modules\Forms\Services\FrontendRuntimeRecoveryService;

class FrontendRuntimeDiagnosticsCommand extends Command
{
    protected $signature = 'frontend:runtime-diagnostics
                            {--json         : Output as JSON}
                            {--no-cache     : Skip cache, run fresh diagnostics}
                            {--auto-recover : Attempt auto-recovery on failures}
                            {--flush-cache  : Flush diagnostics cache and exit}';

    protected $description = 'Run frontend runtime diagnostics and optionally auto-recover failures';

    public function handle(FrontendRuntimeRecoveryService $service): int
    {
        if ($this->option('flush-cache')) {
            $service->flushCache();
            $this->info('Diagnostics cache flushed.');
            return self::SUCCESS;
        }

        $useCache = ! $this->option('no-cache');
        $report   = $service->runDiagnostics($useCache);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $report['healthy'] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->line('<fg=bright-cyan;options=bold> 🔧 Frontend Runtime Diagnostics</>');
        $this->line('  Phase 9.3 — Cross-Context Runtime Parity');
        $this->line('  Timestamp: ' . $report['timestamp']);
        $this->newLine();

        $statusMap = [
            'ok'               => '<fg=bright-green>✓</>',
            'warning'          => '<fg=bright-yellow>⚠</>',
            'failed'           => '<fg=bright-red>✗</>',
            'recovered'        => '<fg=cyan>↻</>',
            'recovery_failed'  => '<fg=red>✗ [recovery failed]</>',
        ];

        foreach ($report['diagnostics'] as $key => $diag) {
            $icon    = $statusMap[$diag['status']] ?? '?';
            $message = $diag['message'] ?? $key;
            $this->line("  {$icon}  {$message}");
            if (! empty($diag['detail'])) {
                $this->line("     <fg=gray>{$diag['detail']}</>");
            }
        }

        $this->newLine();

        if (! empty($report['recoveries'])) {
            $this->line('<options=bold>Auto-Recoveries:</>');
            foreach ($report['recoveries'] as $key => $rec) {
                $icon = $rec['status'] === 'recovered' ? '<fg=cyan>↻</>' : '<fg=red>✗</>';
                $this->line("  {$icon}  {$key}: " . ($rec['message'] ?? $rec['error'] ?? ''));
            }
            $this->newLine();
        }

        $score = $report['score'];
        $bar   = str_repeat('█', (int) ($score / 5)) . str_repeat('░', 20 - (int) ($score / 5));
        $color = $score >= 90 ? 'bright-green' : ($score >= 70 ? 'bright-yellow' : 'bright-red');
        $this->line("  Runtime Score  <fg={$color};options=bold>{$score}/100</>  [{$bar}]");
        $this->newLine();

        if ($report['healthy']) {
            $this->info('  ✅  Frontend runtime is HEALTHY');
        } else {
            $this->error('  ❌  Frontend runtime issues detected');
        }

        return $report['healthy'] ? self::SUCCESS : self::FAILURE;
    }
}
