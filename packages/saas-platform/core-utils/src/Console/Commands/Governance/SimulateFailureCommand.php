<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Governance;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Incident\IncidentResponseCoordinator;
use SaaSPlatform\CoreUtils\RuntimeGovernance\GovernanceContext;

class SimulateFailureCommand extends Command
{
    protected $signature = 'governance:simulate-failure
                            {type : Anomaly/incident type to simulate (e.g. QUEUE_FAILED_JOBS_HIGH)}
                            {--tenant= : Tenant ID for tenant-scoped incidents}
                            {--component= : Component name for circuit-level incidents}
                            {--dry-run  : Show what would happen without executing}
                            {--json     : Output as JSON}';

    protected $description = 'Simulate a runtime failure to test governance response (non-destructive)';

    private const ALLOWED_TYPES = [
        'QUEUE_FAILED_JOBS_HIGH',
        'QUEUE_BACKLOG_HIGH',
        'RUNTIME_MEMORY_HIGH',
        'DEPLOYMENT_FAILURES_RECENT',
        'DEPLOYMENT_STALE_LOCK',
        'SECURITY_AUTH_FAILURE_BURST',
    ];

    public function handle(IncidentResponseCoordinator $coordinator): int
    {
        $type = strtoupper($this->argument('type'));

        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            $this->error('Unknown type: ' . $type);
            $this->line('Allowed types: ' . implode(', ', self::ALLOWED_TYPES));
            return 1;
        }

        $context = [];
        if ($this->option('tenant')) {
            $context['tenant_id'] = (int) $this->option('tenant');
        }
        if ($this->option('component')) {
            $context['component'] = $this->option('component');
        }

        if ($this->option('dry-run')) {
            $info = ['type' => $type, 'context' => $context, 'would_execute' => true];
            $this->option('json')
                ? $this->line(json_encode($info, JSON_PRETTY_PRINT))
                : $this->info('[DRY-RUN] Would simulate: ' . $type . ' with context: ' . json_encode($context));
            return 0;
        }

        $this->warn('Simulating incident: ' . $type);

        $result = $coordinator->respond($type, $context);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return 0;
        }

        $this->info('Incident Response Result:');
        $this->line('  Incident ID  : ' . $result['incident_id']);
        $this->line('  Severity     : ' . $result['severity']);
        $this->line('  Auto Handled : ' . ($result['auto_handled'] ? 'Yes' : 'No'));

        if (!empty($result['escalated'])) {
            $this->warn('  Escalated    : ' . $result['escalation_reason']);
        }

        return 0;
    }
}
