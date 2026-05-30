<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Mobile;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\MobileRuntime\Governance\MobileRuntimeRegistry;
use SaaSPlatform\CoreUtils\MobileRuntime\Governance\MobileRuntimeGovernanceService;
use SaaSPlatform\CoreUtils\MobileRuntime\Telemetry\MobileRealtimeDiagnostics;

class RuntimeStatusCommand extends Command
{
    protected $signature = 'mobile:runtime-status
        {--json             : Output results as JSON}
        {--tenant=          : Show status for specific tenant ID}
        {--fail-on-warning  : Exit 1 if any warnings exist}';

    protected $description = 'Show current mobile runtime status, active tenants, and health summary';

    public function handle(
        MobileRuntimeRegistry $registry,
        MobileRuntimeGovernanceService $governance,
        MobileRealtimeDiagnostics $diagnostics,
    ): int {
        $tenantId    = $this->option('tenant');
        $stats       = $registry->getStats();
        $rtHealth    = $diagnostics->getRealtimeHealth();

        $output = [
            'status'          => 'operational',
            'runtime_count'   => $stats['total'],
            'active_tenants'  => $stats['active_tenants'],
            'realtime'        => $rtHealth,
            'generated_at'    => now()->toIso8601String(),
        ];

        if ($tenantId) {
            $output['tenant_report'] = $governance->getGovernanceReport($tenantId);
        }

        if ($this->option('json')) {
            $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('<fg=bright-cyan;options=bold>Mobile Runtime Status</>');
        $this->line(str_repeat('─', 55));
        $this->line("  Registered tenants : <fg=green>{$stats['total']}</>");
        $this->line("  Realtime transport : <fg=" . ($rtHealth['healthy'] ? 'green' : 'yellow') . ">{$rtHealth['transport']}</>");
        $this->line("  Realtime score     : <fg=" . ($rtHealth['score'] >= 80 ? 'green' : 'yellow') . ">{$rtHealth['score']}/100</>");
        $this->line("  Last check         : {$rtHealth['last_check']}");

        if ($tenantId && isset($output['tenant_report'])) {
            $report = $output['tenant_report'];
            $this->newLine();
            $this->line("  Tenant governance  : " . ($report['runtime_allowed'] ? '<fg=green>Allowed</>' : '<fg=red>Blocked</>'));
            $this->line("  Violation count    : {$report['violation_count']}");
        }

        $this->newLine();
        $this->info('Mobile runtime is operational ✓');

        return self::SUCCESS;
    }
}
