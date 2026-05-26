<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Containment;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class UnsafeExecutionTerminator
{
    private const VIOLATION_THRESHOLD = 3;

    public function __construct(
        private ExtensionQuarantineService $quarantine,
        private SandboxViolationTelemetry  $violationTelemetry,
        private TelemetryManager           $telemetry
    ) {}

    public function evaluateAndTerminate(string $moduleId, string $violationType, array $context = []): bool
    {
        $this->violationTelemetry->record($moduleId, $violationType, $context);
        $count = $this->violationTelemetry->violationCount($moduleId);

        if ($count >= self::VIOLATION_THRESHOLD || $this->isImmediateTermination($violationType)) {
            $this->quarantine->quarantine($moduleId, "auto_terminated: {$violationType} (violations: {$count})");

            $this->telemetry->critical('plugin_governance', 'plugin.execution.terminated', [
                'module_id'      => $moduleId,
                'violation_type' => $violationType,
                'violation_count'=> $count,
            ]);

            return true;
        }

        return false;
    }

    private function isImmediateTermination(string $violationType): bool
    {
        return in_array($violationType, [
            'cross_tenant_violation',
            'deployment_execute_attempt',
            'shell_execution_attempt',
        ], true);
    }
}
