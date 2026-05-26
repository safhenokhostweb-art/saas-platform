<?php

namespace SaaSPlatform\CoreUtils\Scalability;

use SaaSPlatform\CoreUtils\RuntimeIsolation\TenantConsumptionTracker;
use SaaSPlatform\CoreUtils\RuntimeIsolation\TenantQuotaManager;
use SaaSPlatform\CoreUtils\RuntimeIsolation\TenantResourceGovernor;
use SaaSPlatform\CoreUtils\RuntimeIsolation\NoisyNeighbor\TenantPressureAnalyzer;

class TenantStressSimulator
{
    public function __construct(
        private TenantConsumptionTracker $tracker,
        private TenantQuotaManager       $quotaManager,
        private TenantResourceGovernor   $governor,
        private TenantPressureAnalyzer   $pressureAnalyzer
    ) {}

    public function simulateLoad(int $tenantId, array $options = []): array
    {
        $requestCount = $options['requests'] ?? 100;
        $jobCount     = $options['jobs'] ?? 50;
        $dryRun       = $options['dry_run'] ?? true;

        $plan    = $this->quotaManager->getPlan($tenantId);
        $quota   = $this->quotaManager->getQuota($tenantId);
        $results = [];

        if (!$dryRun) {
            for ($i = 0; $i < min($requestCount, 50); $i++) {
                $this->tracker->trackRequest($tenantId, rand(10, 500));
            }
            for ($j = 0; $j < min($jobCount, 20); $j++) {
                $this->tracker->trackQueueJob($tenantId, rand(1, 10));
            }
        }

        $enforcement  = $this->governor->enforce($tenantId);
        $pressureScore = $this->pressureAnalyzer->pressureScore($tenantId);
        $pressureLevel = $this->pressureAnalyzer->pressureLevel($tenantId);

        return [
            'tenant_id'      => $tenantId,
            'plan'           => $plan,
            'simulated_requests' => $dryRun ? 0 : $requestCount,
            'simulated_jobs' => $dryRun ? 0 : $jobCount,
            'dry_run'        => $dryRun,
            'pressure_score' => $pressureScore,
            'pressure_level' => $pressureLevel,
            'enforcement'    => $enforcement->toArray(),
            'quota'          => $quota,
            'consumption'    => $this->tracker->consumptionSummary($tenantId),
        ];
    }

    public function simulateNoisyNeighbor(int $noisyTenantId, array $victimTenantIds): array
    {
        $noisyStatus   = $this->pressureAnalyzer->pressureLevel($noisyTenantId);
        $victimStatuses = [];

        foreach ($victimTenantIds as $id) {
            $victimStatuses[$id] = [
                'pressure_level' => $this->pressureAnalyzer->pressureLevel($id),
                'pressure_score' => $this->pressureAnalyzer->pressureScore($id),
            ];
        }

        return [
            'noisy_tenant'    => ['id' => $noisyTenantId, 'pressure' => $noisyStatus],
            'victim_tenants'  => $victimStatuses,
            'isolation_holds' => $noisyStatus === 'noisy',
        ];
    }
}
