<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class RuntimeCapabilityEnforcer
{
    public function __construct(
        private CapabilityResolutionEngine $resolver,
        private TelemetryManager           $telemetry
    ) {}

    public function enforce(string $moduleId, string $capability, array $context = []): bool
    {
        $allowed = $this->resolver->resolve($moduleId, $capability);

        if (!$allowed) {
            $this->telemetry->warning('plugin_governance', 'plugin.capability.denied', array_merge([
                'module_id'  => $moduleId,
                'capability' => $capability,
            ], $context));

            return false;
        }

        $this->telemetry->debug('plugin_governance', 'plugin.capability.allowed', [
            'module_id'  => $moduleId,
            'capability' => $capability,
        ]);

        return true;
    }

    public function enforceOrFail(string $moduleId, string $capability, array $context = []): void
    {
        if (!$this->enforce($moduleId, $capability, $context)) {
            throw new CapabilityDeniedException(
                "Module [{$moduleId}] does not have capability [{$capability}]."
            );
        }
    }

    public function enforceContext(string $moduleId, string $capability, string $executionContext): bool
    {
        $allowed = $this->resolver->canExecuteInContext($moduleId, $capability, $executionContext);

        if (!$allowed) {
            $this->telemetry->warning('plugin_governance', 'plugin.capability.context_denied', [
                'module_id'         => $moduleId,
                'capability'        => $capability,
                'execution_context' => $executionContext,
            ]);

            return false;
        }

        return true;
    }

    public function profile(string $moduleId): ModulePermissionProfile
    {
        return $this->resolver->resolutionProfile($moduleId);
    }
}
