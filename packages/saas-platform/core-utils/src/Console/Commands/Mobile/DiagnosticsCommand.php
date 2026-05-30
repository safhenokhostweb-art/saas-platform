<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Mobile;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\MobileRuntime\Telemetry\MobileRuntimeTelemetryService;
use SaaSPlatform\CoreUtils\MobileRuntime\Telemetry\MobileConnectionMetrics;
use SaaSPlatform\CoreUtils\MobileRuntime\Telemetry\MobileRealtimeDiagnostics;
use SaaSPlatform\CoreUtils\MobileRuntime\Telemetry\MobileCrashTelemetry;
use SaaSPlatform\CoreUtils\MobileRuntime\Telemetry\MobileDeliveryAnalytics;
use SaaSPlatform\CoreUtils\MobileRuntime\Governance\MobileRuntimeRegistry;
use SaaSPlatform\CoreUtils\MobileRuntime\Governance\MobileRuntimeValidationService;

class DiagnosticsCommand extends Command
{
    protected $signature = 'mobile:diagnostics
        {--json             : Output full diagnostics as JSON}
        {--tenant=          : Run diagnostics for a specific tenant ID}
        {--fail-on-warning  : Exit 1 if any warnings exist}
        {--full             : Include crash report and delivery analytics}';

    protected $description = 'Run full mobile runtime diagnostics — telemetry, realtime health, governance, and delivery analytics';

    public function handle(
        MobileRuntimeTelemetryService $telemetry,
        MobileConnectionMetrics $connectionMetrics,
        MobileRealtimeDiagnostics $realtimeDiagnostics,
        MobileCrashTelemetry $crashTelemetry,
        MobileDeliveryAnalytics $deliveryAnalytics,
        MobileRuntimeRegistry $registry,
        MobileRuntimeValidationService $validationService,
    ): int {
        $tenantId = $this->option('tenant') ?? 'platform';
        $full     = (bool) $this->option('full');
        $failWarn = (bool) $this->option('fail-on-warning');

        // Gather all diagnostic data
        $rtDiag        = $realtimeDiagnostics->runDiagnostics($tenantId);
        $rtHealth      = $realtimeDiagnostics->getRealtimeHealth();
        $connStats     = $connectionMetrics->getConnectionStats($tenantId);   // correct method
        $validation    = $validationService->validate();
        $registryStats = $registry->getStats();

        $diagnostics = [
            'tenant'        => $tenantId,
            'generated_at'  => now()->toIso8601String(),
            'runtime'       => [
                'registered_tenants' => $registryStats['total'],
                'active_tenants'     => count($registryStats['active_tenants']),  // array → count
                'governance_score'   => $validation['score'],
                'governance_grade'   => $validation['grade'],
            ],
            'realtime'      => $rtHealth,
            'transport'     => $rtDiag,
            'connections'   => $connStats,
        ];

        if ($full) {
            $crashes     = $crashTelemetry->getCrashGroups($tenantId, 5);   // limit param now supported
            $dailyReport = $deliveryAnalytics->getDailyReport($tenantId);
            $diagnostics['crash_report']       = $crashes;
            $diagnostics['delivery_analytics'] = $dailyReport;
        }

        // Compute overall health
        $scores      = [$rtDiag['score'], $validation['score']];
        $avgScore    = (int) round(array_sum($scores) / count($scores));
        $hasWarnings = $avgScore < 90;

        $diagnostics['overall_score']  = $avgScore;
        $diagnostics['overall_status'] = $avgScore >= 90 ? 'healthy' : ($avgScore >= 70 ? 'degraded' : 'unhealthy');

        if ($this->option('json')) {
            $this->line(json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            if ($failWarn && $hasWarnings) return self::FAILURE;
            return $avgScore >= 70 ? self::SUCCESS : self::FAILURE;
        }

        $color = $avgScore >= 90 ? 'green' : ($avgScore >= 70 ? 'yellow' : 'red');

        $this->newLine();
        $this->line('<fg=bright-cyan;options=bold>Mobile Runtime Diagnostics</>');
        $this->line(str_repeat('─', 55));

        // Runtime section
        $this->line('<options=bold>  Runtime Registry</>');
        $this->line("    Registered tenants  : <fg=green>{$registryStats['total']}</>");
        $this->line("    Active tenants       : <fg=green>" . count($registryStats['active_tenants']) . "</>"); // count array
        $this->line("    Governance score     : <fg=" . ($validation['score'] >= 90 ? 'green' : 'yellow') . ">{$validation['score']}/100 (Grade {$validation['grade']})</>");
        $this->newLine();

        // Realtime section
        $rtColor = $rtHealth['healthy'] ? 'green' : 'yellow';
        $this->line('<options=bold>  Realtime Transport</>');
        $this->line("    Transport            : <fg={$rtColor}>{$rtHealth['transport']}</>");
        $this->line("    Health score         : <fg={$rtColor}>{$rtHealth['score']}/100</>");
        $this->line("    Last check           : {$rtHealth['last_check']}");
        $this->newLine();

        // Connection metrics (getConnectionStats keys: active, total_today, reconnect_count, avg_duration_s, avg_latency_ms)
        $this->line('<options=bold>  Connection Metrics</>');
        $this->line("    Active connections   : " . ($connStats['active'] ?? 0));
        $this->line("    Total today          : " . ($connStats['total_today'] ?? 0));
        $this->line("    Avg latency (ms)     : " . round($connStats['avg_latency_ms'] ?? 0, 1));
        $this->line("    Reconnects           : " . ($connStats['reconnect_count'] ?? 0));
        $this->newLine();

        // Realtime checks
        $this->line('<options=bold>  Diagnostic Checks</>');
        foreach ($rtDiag['checks'] as $check) {
            $icon = match ($check['status']) {
                'pass'  => '<fg=green>✓</>',
                'warn'  => '<fg=yellow>⚠</>',
                'fail'  => '<fg=red>✗</>',
                default => '?',
            };
            $this->line("    {$icon}  {$check['name']}: {$check['message']}");
        }

        if ($full) {
            $this->newLine();
            $this->line('<options=bold>  Crash Report (top 5)</>');
            if (empty($diagnostics['crash_report'])) {
                $this->line('    <fg=green>No crash groups detected</>');
            } else {
                foreach ($diagnostics['crash_report'] as $group) {
                    // correct key is 'error_message', not 'message'
                    $this->line("    [{$group['count']}x] {$group['error_type']}: " . mb_substr($group['error_message'] ?? '', 0, 60));
                }
            }

            $this->newLine();
            $this->line('<options=bold>  Delivery Analytics (today)</>');
            // getDailyReport wraps stats under 'stats' key
            $stats = $diagnostics['delivery_analytics']['stats'] ?? [];
            $this->line("    Messages delivered   : " . ($stats['messages']['delivered'] ?? 0));
            $this->line("    Notifications sent   : " . ($stats['notifications']['delivered'] ?? 0));
            $this->line("    Push delivered       : " . ($stats['push']['delivered'] ?? 0));
        }

        $this->newLine();
        $this->line(str_repeat('─', 55));
        $this->line("<fg={$color};options=bold>Overall Diagnostics Score: {$avgScore}/100</> — Status: {$diagnostics['overall_status']}");
        $this->newLine();

        if (! empty($rtDiag['issues'])) {
            $this->warn('Active issues:');
            foreach ($rtDiag['issues'] as $issue) {
                $this->line("  • {$issue}");
            }
            $this->newLine();
        }

        if ($failWarn && $hasWarnings) return self::FAILURE;
        return $avgScore >= 70 ? self::SUCCESS : self::FAILURE;
    }
}
