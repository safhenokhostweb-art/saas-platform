<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Sandbox;

use SaaSPlatform\CoreUtils\PluginGovernance\CapabilityGrantManager;
use SaaSPlatform\CoreUtils\PluginGovernance\CapabilityResolutionEngine;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class RuntimeSandboxBoundary
{
    public function __construct(
        private CapabilityResolutionEngine $resolver,
        private CapabilityGrantManager     $grantManager,
        private TelemetryManager           $telemetry
    ) {}

    public function createContext(string $moduleId, ?int $tenantId = null, string $executionContext = PluginExecutionContext::CONTEXT_PLUGIN): PluginExecutionContext
    {
        $granted = $this->resolver->effectiveCapabilities($moduleId);

        $this->telemetry->info('plugin_governance', 'plugin.sandbox.context_created', [
            'module_id'  => $moduleId,
            'tenant_id'  => $tenantId,
            'context'    => $executionContext,
            'granted'    => count($granted),
        ]);

        return PluginExecutionContext::make($moduleId, $executionContext, $tenantId, $granted);
    }

    public function validateBoundary(PluginExecutionContext $context, string $capability): bool
    {
        if (!$context->can($capability)) {
            $this->telemetry->warning('plugin_governance', 'plugin.sandbox.boundary_violation', [
                'module_id'    => $context->moduleId(),
                'capability'   => $capability,
                'execution_id' => $context->executionId(),
            ]);
            return false;
        }

        return true;
    }

    public function isCrossTenantAttempt(PluginExecutionContext $context, ?int $targetTenantId): bool
    {
        if ($context->tenantId() === null || $targetTenantId === null) {
            return false;
        }

        $isCross = $context->tenantId() !== $targetTenantId;

        if ($isCross) {
            $this->telemetry->critical('plugin_governance', 'plugin.sandbox.cross_tenant_attempt', [
                'module_id'       => $context->moduleId(),
                'context_tenant'  => $context->tenantId(),
                'target_tenant'   => $targetTenantId,
                'execution_id'    => $context->executionId(),
            ]);
        }

        return $isCross;
    }
}
