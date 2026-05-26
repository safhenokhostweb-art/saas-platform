<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Containment;

use SaaSPlatform\CoreUtils\PluginGovernance\Sandbox\PluginExecutionContext;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class PluginContainmentManager
{
    public function __construct(
        private RuntimeViolationDetector   $violationDetector,
        private ExtensionQuarantineService $quarantine,
        private UnsafeExecutionTerminator  $terminator,
        private SandboxViolationTelemetry  $violationTelemetry,
        private TelemetryManager           $telemetry
    ) {}

    public function guardExecution(PluginExecutionContext $context, callable $callback): mixed
    {
        if ($this->quarantine->isQuarantined($context->moduleId())) {
            throw new \RuntimeException("Module [{$context->moduleId()}] is quarantined and cannot execute.");
        }

        try {
            $result = $callback();

            if ($this->violationDetector->detectTimeoutViolation($context)) {
                $this->terminator->evaluateAndTerminate(
                    $context->moduleId(),
                    'timeout_violation',
                    ['execution_id' => $context->executionId()]
                );
            }

            return $result;
        } catch (\Throwable $e) {
            $this->violationTelemetry->record($context->moduleId(), 'execution_exception', [
                'error'        => $e->getMessage(),
                'execution_id' => $context->executionId(),
            ]);
            throw $e;
        }
    }

    public function checkCapabilityBoundary(PluginExecutionContext $context, string $capability): void
    {
        if ($this->violationDetector->detectCapabilityViolation($context, $capability)) {
            $this->terminator->evaluateAndTerminate(
                $context->moduleId(),
                'capability_violation',
                ['capability' => $capability]
            );
            throw new \RuntimeException("Capability [{$capability}] denied for [{$context->moduleId()}].");
        }
    }

    public function isContained(string $moduleId): bool
    {
        return $this->quarantine->isQuarantined($moduleId);
    }

    public function violationSummary(string $moduleId): array
    {
        return [
            'quarantined'       => $this->quarantine->isQuarantined($moduleId),
            'violation_count'   => $this->violationTelemetry->violationCount($moduleId),
            'violations'        => $this->violationTelemetry->getViolations($moduleId),
        ];
    }
}
