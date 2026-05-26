<?php

namespace SaaSPlatform\CoreUtils\RuntimeIsolation;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class TenantConsumptionTracker
{
    public function __construct(
        private TenantRuntimeBudget $budget,
        private TenantQuotaManager  $quotaManager,
        private TelemetryManager    $telemetry
    ) {}

    public function trackRequest(int $tenantId, float $elapsedMs = 1.0): void
    {
        $this->budget->charge($tenantId, 'max_requests_per_minute', 1);
        $this->budget->charge($tenantId, 'max_runtime_seconds_per_hour', $elapsedMs / 1000);
        $this->checkCeilings($tenantId);
    }

    public function trackQueueJob(int $tenantId, float $durationSeconds = 1.0): void
    {
        $this->budget->charge($tenantId, 'max_queue_jobs_per_hour', 1);
        $this->budget->charge($tenantId, 'max_runtime_seconds_per_hour', $durationSeconds);
        $this->checkCeilings($tenantId);
    }

    public function trackStorageWrite(int $tenantId, float $sizeMb): void
    {
        $this->budget->charge($tenantId, 'max_storage_mb', $sizeMb);
        $this->checkStorageCeiling($tenantId);
    }

    public function consumptionSummary(int $tenantId): array
    {
        $quota   = $this->quotaManager->getQuota($tenantId);
        $snap    = $this->budget->snapshot($tenantId);
        $summary = [];

        foreach ($snap as $resource => $consumed) {
            $ceiling  = $quota[$resource] ?? 0;
            $summary[$resource] = [
                'consumed'    => $consumed,
                'ceiling'     => $ceiling,
                'utilization' => $ceiling > 0 ? round($consumed / $ceiling * 100, 1) : 0,
                'exhausted'   => $ceiling > 0 && $consumed >= $ceiling,
            ];
        }

        return $summary;
    }

    public function isThrottleable(int $tenantId): bool
    {
        $quota = $this->quotaManager->getQuota($tenantId);

        return $this->budget->isNearCeiling($tenantId, 'max_requests_per_minute', $quota['max_requests_per_minute'] ?? 300)
            || $this->budget->isNearCeiling($tenantId, 'max_queue_jobs_per_hour', $quota['max_queue_jobs_per_hour'] ?? 500);
    }

    private function checkCeilings(int $tenantId): void
    {
        $quota = $this->quotaManager->getQuota($tenantId);

        foreach (['max_requests_per_minute', 'max_queue_jobs_per_hour', 'max_runtime_seconds_per_hour'] as $resource) {
            $ceiling = $quota[$resource] ?? 0;
            if ($ceiling > 0 && $this->budget->isNearCeiling($tenantId, $resource, $ceiling, 90.0)) {
                $this->telemetry->warning('isolation', 'isolation.tenant.budget_near_ceiling', [
                    'tenant_id'   => $tenantId,
                    'resource'    => $resource,
                    'consumed'    => $this->budget->consumed($tenantId, $resource),
                    'ceiling'     => $ceiling,
                ]);
            }
        }
    }

    private function checkStorageCeiling(int $tenantId): void
    {
        $ceiling = $this->quotaManager->getLimit($tenantId, 'max_storage_mb');
        if ($ceiling > 0 && $this->budget->isNearCeiling($tenantId, 'max_storage_mb', $ceiling, 85.0)) {
            $this->telemetry->warning('isolation', 'isolation.tenant.storage_near_ceiling', [
                'tenant_id' => $tenantId,
                'consumed'  => $this->budget->consumed($tenantId, 'max_storage_mb'),
                'ceiling'   => $ceiling,
            ]);
        }
    }
}
