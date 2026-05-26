<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Sandbox;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class IsolatedExecutionCoordinator
{
    public function __construct(
        private RuntimeSandboxBoundary $boundary,
        private ModuleResourceLimiter  $limiter,
        private TelemetryManager       $telemetry
    ) {}

    public function execute(
        string   $moduleId,
        string   $capability,
        callable $callback,
        ?int     $tenantId = null,
        string   $executionContext = PluginExecutionContext::CONTEXT_PLUGIN
    ): mixed {
        $context = $this->boundary->createContext($moduleId, $tenantId, $executionContext);

        if (!$this->boundary->validateBoundary($context, $capability)) {
            throw new \RuntimeException("Capability [{$capability}] denied for module [{$moduleId}].");
        }

        $container = new PluginRuntimeContainer($this->telemetry);
        return $container->run($context, $callback);
    }

    public function executeIfAllowed(
        string   $moduleId,
        string   $capability,
        callable $callback,
        ?int     $tenantId = null
    ): ?array {
        try {
            $result = $this->execute($moduleId, $capability, $callback, $tenantId);
            return ['success' => true, 'result' => $result];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function canExecute(string $moduleId, string $capability, ?int $tenantId = null): bool
    {
        $context = $this->boundary->createContext($moduleId, $tenantId);
        return $this->boundary->validateBoundary($context, $capability);
    }
}
