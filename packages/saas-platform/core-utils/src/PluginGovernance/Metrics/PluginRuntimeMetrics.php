<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Metrics;

use SaaSPlatform\CoreUtils\PluginGovernance\Sandbox\SandboxRuntimeManager;
use SaaSPlatform\CoreUtils\PluginGovernance\Containment\SandboxViolationTelemetry;
use SaaSPlatform\CoreUtils\PluginGovernance\Containment\ExtensionQuarantineService;
use SaaSPlatform\CoreUtils\PluginGovernance\ResourceGovernance\PluginResourceBudget;
use SaaSPlatform\CoreUtils\PluginGovernance\ModuleCapabilityRegistry;
use SaaSPlatform\CoreUtils\PluginGovernance\Trust\TrustedPublisherRegistry;

class PluginRuntimeMetrics
{
    public function __construct(
        private SandboxRuntimeManager    $sandboxManager,
        private SandboxViolationTelemetry $violationTelemetry,
        private ExtensionQuarantineService $quarantine,
        private PluginResourceBudget     $budget,
        private ModuleCapabilityRegistry $capabilityRegistry,
        private TrustedPublisherRegistry $publisherRegistry
    ) {}

    public function forModule(string $moduleId): array
    {
        $isolationMetrics   = SandboxIsolationMetrics::capture($moduleId, $this->violationTelemetry, $this->quarantine);
        $capabilityMetrics  = CapabilityUsageMetrics::forModule($moduleId, app(\SaaSPlatform\CoreUtils\PluginGovernance\CapabilityResolutionEngine::class), $this->capabilityRegistry);
        $budgetSnapshot     = $this->budget->snapshot($moduleId);
        $sandboxStatus      = $this->sandboxManager->status($moduleId);

        return [
            'module_id'          => $moduleId,
            'sandbox'            => $sandboxStatus,
            'isolation'          => $isolationMetrics,
            'capabilities'       => $capabilityMetrics,
            'resource_budget'    => $budgetSnapshot,
            'captured_at'        => now()->toIso8601String(),
        ];
    }

    public function platformOverview(): array
    {
        return [
            'capabilities'         => CapabilityUsageMetrics::platformSummary($this->capabilityRegistry),
            'trusted_publishers'   => $this->publisherRegistry->count(),
            'sandbox_runtime'      => $this->sandboxManager->summary(),
            'captured_at'          => now()->toIso8601String(),
        ];
    }
}
