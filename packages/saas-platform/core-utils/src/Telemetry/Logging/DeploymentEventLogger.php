<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Logging;

use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryContext;

class DeploymentEventLogger
{
    public function __construct(private TelemetryContext $context) {}

    public function deploymentStarted(string $deploymentUuid, array $context = []): void
    {
        $this->write('deployment.started', 'info', $deploymentUuid, $context);
    }

    public function deploymentCompleted(string $deploymentUuid, float $durationMs, array $context = []): void
    {
        $this->write('deployment.completed', 'info', $deploymentUuid, array_merge([
            'duration_ms' => $durationMs,
        ], $context));
    }

    public function deploymentFailed(string $deploymentUuid, string $reason, array $context = []): void
    {
        $this->write('deployment.failed', 'critical', $deploymentUuid, array_merge([
            'reason' => $reason,
        ], $context));
    }

    public function deploymentRolledBack(string $deploymentUuid, string $targetUuid, array $context = []): void
    {
        $this->write('deployment.rolled_back', 'warning', $deploymentUuid, array_merge([
            'target_uuid' => $targetUuid,
        ], $context));
    }

    public function phaseTransition(string $deploymentUuid, string $from, string $to): void
    {
        $this->write('deployment.phase_transition', 'info', $deploymentUuid, [
            'from_phase' => $from,
            'to_phase'   => $to,
        ]);
    }

    public function migrationApplied(string $deploymentUuid, string $migration): void
    {
        $this->write('deployment.migration.applied', 'info', $deploymentUuid, [
            'migration' => $migration,
        ]);
    }

    private function write(string $event, string $level, string $deploymentUuid, array $context): void
    {
        Log::$level($event, array_merge($this->context->toArray(), [
            'event'           => $event,
            'deployment_uuid' => $deploymentUuid,
            'release_id'      => $this->context->getReleaseId() ?? $deploymentUuid,
            'environment'     => config('app.env', 'production'),
            'timestamp'       => now()->toIso8601String(),
            'context'         => $context,
        ]));
    }
}
