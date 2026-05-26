<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Degradation;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use SaaSPlatform\CoreUtils\RuntimeGovernance\CircuitBreaker\TenantCircuitRegistry;

class TenantProtectionCoordinator
{
    public function __construct(
        private TenantDegradationManager $degradationManager,
        private TenantIsolationPolicy    $isolationPolicy,
        private TenantCircuitRegistry    $circuitRegistry,
        private TelemetryManager         $telemetry
    ) {}

    public function protect(int $tenantId, string $reason = 'auto_protection'): array
    {
        $currentLevel = $this->degradationManager->currentLevel($tenantId);
        $newLevel     = min(5, $currentLevel + 1);

        $this->degradationManager->degrade($tenantId, $newLevel, $reason);

        if ($newLevel >= 5) {
            $this->circuitRegistry->openForTenant($tenantId);
        }

        return [
            'tenant_id'     => $tenantId,
            'previous_level' => $currentLevel,
            'new_level'      => $newLevel,
            'level_name'     => $this->degradationManager->levelName($newLevel),
            'circuit_opened' => $newLevel >= 5,
            'reason'         => $reason,
        ];
    }

    public function restore(int $tenantId, string $reason = 'recovery'): array
    {
        $currentLevel = $this->degradationManager->currentLevel($tenantId);

        $this->degradationManager->restore($tenantId, $reason);

        if ($this->circuitRegistry->tenantIsIsolated($tenantId)) {
            $this->circuitRegistry->resetForTenant($tenantId);
        }

        return [
            'tenant_id'      => $tenantId,
            'previous_level' => $currentLevel,
            'new_level'      => 0,
            'circuit_reset'  => true,
            'reason'         => $reason,
        ];
    }

    public function isCapabilityAllowed(int $tenantId, string $capability): bool
    {
        $level = $this->degradationManager->currentLevel($tenantId);
        return $this->isolationPolicy->isPermitted($tenantId, $capability, $level);
    }

    public function tenantStatus(int $tenantId): array
    {
        $level = $this->degradationManager->currentLevel($tenantId);

        return [
            'tenant_id'    => $tenantId,
            'level'        => $level,
            'level_name'   => $this->degradationManager->levelName($level),
            'description'  => $this->isolationPolicy->describe($level),
            'capabilities' => $this->degradationManager->capabilities($tenantId),
            'isolated'     => $this->circuitRegistry->tenantIsIsolated($tenantId),
        ];
    }
}
