<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Containment;

use SaaSPlatform\CoreUtils\PluginGovernance\Sandbox\PluginExecutionContext;

class RuntimeViolationDetector
{
    private const MAX_EXECUTION_MS = 30000;

    public function __construct(
        private SandboxViolationTelemetry $violationTelemetry
    ) {}

    public function detectCapabilityViolation(PluginExecutionContext $context, string $capability): bool
    {
        if (!$context->can($capability)) {
            $this->violationTelemetry->record($context->moduleId(), 'capability_violation', [
                'capability'   => $capability,
                'execution_id' => $context->executionId(),
            ]);
            return true;
        }
        return false;
    }

    public function detectCrossTenantViolation(PluginExecutionContext $context, ?int $targetTenant): bool
    {
        if ($context->tenantId() !== null && $targetTenant !== null && $context->tenantId() !== $targetTenant) {
            $this->violationTelemetry->record($context->moduleId(), 'cross_tenant_violation', [
                'context_tenant' => $context->tenantId(),
                'target_tenant'  => $targetTenant,
                'execution_id'   => $context->executionId(),
            ]);
            return true;
        }
        return false;
    }

    public function detectTimeoutViolation(PluginExecutionContext $context): bool
    {
        if ($context->elapsedMs() > self::MAX_EXECUTION_MS) {
            $this->violationTelemetry->record($context->moduleId(), 'timeout_violation', [
                'elapsed_ms'   => $context->elapsedMs(),
                'max_ms'       => self::MAX_EXECUTION_MS,
                'execution_id' => $context->executionId(),
            ]);
            return true;
        }
        return false;
    }

    public function hasViolations(string $moduleId): bool
    {
        return $this->violationTelemetry->violationCount($moduleId) > 0;
    }
}
