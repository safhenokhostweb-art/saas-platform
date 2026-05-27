<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\AI;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\AI\AiProviderManager;
use SaaSPlatform\CoreUtils\AI\AiProviderHealthMonitor;
use SaaSPlatform\CoreUtils\AI\Observability\AiProviderDiagnostics;
use SaaSPlatform\CoreUtils\AI\Observability\AiTelemetryService;
use SaaSPlatform\CoreUtils\AI\Observability\AiFailureAnalytics;

class AiDiagnosticsCommand extends Command
{
    protected $signature = 'ai:diagnostics
        {--json : Output as JSON}
        {--probe : Run live provider probes (makes real API calls)}';

    protected $description = 'Show full AI subsystem diagnostics including providers, health, telemetry, and failures';

    public function handle(
        AiProviderManager       $manager,
        AiProviderHealthMonitor $healthMonitor,
        AiProviderDiagnostics   $diagnostics,
        AiTelemetryService      $telemetry,
        AiFailureAnalytics      $failures
    ): int {
        $jsonMode = (bool) $this->option('json');
        $probe    = (bool) $this->option('probe');

        $report = $diagnostics->getDiagnosticsReport();
        $stats  = $telemetry->getStats('__system__', 7);
        $failSummary = $failures->getFailureSummary(7);

        if ($probe) {
            $probeResults = $healthMonitor->runProbeAll();
            $report['probe_results'] = $probeResults;
        }

        if ($jsonMode) {
            $this->line(json_encode([
                'providers'     => $report,
                'telemetry_7d'  => $stats,
                'failures_7d'   => $failSummary,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->line('<fg=bright-blue;options=bold>AI Subsystem Diagnostics</>');
        $this->line(str_repeat('─', 60));

        // Provider status
        $this->line('<options=bold>Providers</>');
        foreach ($report['providers'] as $name => $p) {
            $icon  = $p['healthy'] ? '<fg=green>●</>' : ($p['enabled'] ? '<fg=yellow>◑</>' : '<fg=gray>○</>');
            $circuit = $p['circuit_open'] ? ' <fg=red>[CIRCUIT OPEN]</>' : '';
            $latency = $p['avg_latency'] > 0 ? " {$p['avg_latency']}ms avg" : '';
            $this->line("  {$icon} {$name}{$circuit}{$latency} — health: {$p['health_score']}");
        }

        if ($probe && isset($report['probe_results'])) {
            $this->newLine();
            $this->line('<options=bold>Live Probes</>');
            foreach ($report['probe_results'] as $name => $p) {
                if (! $p['probed']) {
                    $this->line("  <fg=gray>○ {$name} — {$p['reason']}</>");
                } elseif ($p['success']) {
                    $this->line("  <fg=green>✓ {$name}</> — {$p['latency']}ms");
                } else {
                    $this->line("  <fg=red>✗ {$name}</> — {$p['error']}");
                }
            }
        }

        // 7-day telemetry
        $this->newLine();
        $this->line('<options=bold>Last 7 Days Telemetry</>');
        $this->line("  Total requests: {$stats['total']}");
        $this->line("  Success rate:   {$stats['success_rate']}%");
        $this->line("  Avg latency:    {$stats['avg_latency']}ms");
        $this->line("  Total tokens:   " . number_format($stats['tokens']));

        // Failures
        $this->newLine();
        $this->line('<options=bold>Failures (7d)</>');
        $this->line("  Total:       {$failSummary['total']}");
        if (! empty($failSummary['by_type'])) {
            foreach (array_slice($failSummary['by_type'], 0, 5, true) as $type => $count) {
                $this->line("  {$type}: {$count}");
            }
        }

        // Emergency status
        $this->newLine();
        $this->line('<options=bold>Emergency Controls</>');
        $killSwitch   = config('ai.emergency.kill_switch', false) ? '<fg=red>ACTIVE</>' : '<fg=green>inactive</>';
        $degraded     = config('ai.emergency.degraded_mode', false) ? '<fg=yellow>ACTIVE</>' : '<fg=green>inactive</>';
        $this->line("  Kill switch:   {$killSwitch}");
        $this->line("  Degraded mode: {$degraded}");

        if (! empty($report['recommendations'])) {
            $this->newLine();
            $this->line('<options=bold>Recommendations</>');
            foreach ($report['recommendations'] as $rec) {
                $this->line("  <fg=yellow>!</> {$rec}");
            }
        }

        $this->newLine();
        return Command::SUCCESS;
    }
}
