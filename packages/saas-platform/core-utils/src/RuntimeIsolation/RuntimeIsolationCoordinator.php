<?php

namespace SaaSPlatform\CoreUtils\RuntimeIsolation;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class RuntimeIsolationCoordinator
{
    public function __construct(
        private TenantResourceGovernor  $governor,
        private TenantConsumptionTracker $tracker,
        private TenantQuotaManager      $quotaManager,
        private TelemetryManager        $telemetry
    ) {}

    public function onRequest(int $tenantId, float $elapsedMs): EnforcementResult
    {
        $this->tracker->trackRequest($tenantId, $elapsedMs);
        return $this->governor->enforce($tenantId);
    }

    public function onQueueJob(int $tenantId, float $durationSeconds): EnforcementResult
    {
        $this->tracker->trackQueueJob($tenantId, $durationSeconds);
        return $this->governor->enforce($tenantId);
    }

    public function onStorageWrite(int $tenantId, float $sizeMb): EnforcementResult
    {
        $this->tracker->trackStorageWrite($tenantId, $sizeMb);
        return $this->governor->enforce($tenantId);
    }

    public function provisionTenant(int $tenantId, string $plan, array $overrides = []): void
    {
        $this->quotaManager->setQuota($tenantId, $plan, $overrides);

        $this->telemetry->info('isolation', 'isolation.tenant.provisioned', [
            'tenant_id' => $tenantId,
            'plan'      => $plan,
        ]);
    }

    public function isolationStatus(int $tenantId): array
    {
        return $this->governor->tenantStatus($tenantId);
    }

    public function platformIsolationHealth(array $tenantIds): array
    {
        $statuses   = [];
        $throttled  = 0;
        $emergency  = 0;

        foreach ($tenantIds as $id) {
            $status = $this->governor->tenantStatus($id);
            $statuses[$id] = $status;
            if ($status['throttleable']) {
                $throttled++;
            }
            if ($status['emergency']) {
                $emergency++;
            }
        }

        return [
            'tenant_count'    => count($tenantIds),
            'throttled_count' => $throttled,
            'emergency_count' => $emergency,
            'statuses'        => $statuses,
        ];
    }
}
