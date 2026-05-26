<?php

namespace SaaSPlatform\CoreUtils\Scalability;

use SaaSPlatform\CoreUtils\RuntimeIsolation\RuntimeIsolationCoordinator;
use SaaSPlatform\CoreUtils\RuntimeIsolation\NoisyNeighbor\RuntimeFairnessCoordinator;
use SaaSPlatform\CoreUtils\DistributedQueue\DistributedQueueCoordinator;
use SaaSPlatform\CoreUtils\CapacityGovernance\RuntimePressurePredictor;
use SaaSPlatform\CoreUtils\NodeCoordination\RuntimeNodeCoordinator;
use SaaSPlatform\CoreUtils\CacheGovernance\DistributedCacheIsolation;
use SaaSPlatform\CoreUtils\StorageGovernance\AttachmentIsolationManager;
use SaaSPlatform\CoreUtils\RuntimeScheduling\RuntimeScheduler;

class ScalabilityValidationService
{
    public function __construct(
        private RuntimeIsolationCoordinator  $isolationCoordinator,
        private RuntimeFairnessCoordinator   $fairnessCoordinator,
        private DistributedQueueCoordinator  $queueCoordinator,
        private RuntimePressurePredictor     $pressurePredictor,
        private RuntimeNodeCoordinator       $nodeCoordinator,
        private DistributedCacheIsolation    $cacheIsolation,
        private AttachmentIsolationManager   $attachmentIsolation,
        private RuntimeScheduler             $scheduler
    ) {}

    public function validate(array $tenantIds = []): array
    {
        $checks  = [];
        $passed  = 0;
        $failed  = 0;
        $warnings = 0;

        $dimensions = [
            'tenant_isolation'    => $this->validateTenantIsolation($tenantIds),
            'noisy_neighbor'      => $this->validateNoisyNeighborResistance($tenantIds),
            'queue_scalability'   => $this->validateQueueScalability(),
            'cache_isolation'     => $this->validateCacheIsolation(),
            'storage_isolation'   => $this->validateStorageIsolation(),
            'scheduling_fairness' => $this->validateSchedulingFairness(),
            'pressure_predictor'  => $this->validatePressurePredictor(),
            'node_coordination'   => $this->validateNodeCoordination(),
        ];

        foreach ($dimensions as $name => $result) {
            $checks[$name] = $result;
            if ($result['status'] === 'pass') {
                $passed++;
            } elseif ($result['status'] === 'warn') {
                $warnings++;
            } else {
                $failed++;
            }
        }

        return [
            'valid'        => $failed === 0,
            'passed'       => $passed,
            'warnings'     => $warnings,
            'failed'       => $failed,
            'total'        => count($checks),
            'checks'       => $checks,
            'validated_at' => now()->toIso8601String(),
        ];
    }

    private function validateTenantIsolation(array $tenantIds): array
    {
        try {
            $health = $this->isolationCoordinator->platformIsolationHealth($tenantIds ?: [1]);
            $emergency = $health['emergency_count'] ?? 0;
            return $emergency === 0
                ? ['status' => 'pass', 'detail' => 'Tenant isolation enforced — no emergency states']
                : ['status' => 'warn', 'detail' => $emergency . ' tenant(s) under emergency quota'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }

    private function validateNoisyNeighborResistance(array $tenantIds): array
    {
        try {
            $score = $this->fairnessCoordinator->fairnessScore($tenantIds ?: []);
            $status = $score >= 70 ? 'pass' : ($score >= 50 ? 'warn' : 'fail');
            return ['status' => $status, 'detail' => 'Fairness score: ' . $score];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }

    private function validateQueueScalability(): array
    {
        try {
            $status = $this->queueCoordinator->coordinationStatus();
            $recs   = $status['recommendations'] ?? [];
            return empty($recs)
                ? ['status' => 'pass', 'detail' => 'Queue coordination healthy']
                : ['status' => 'warn', 'detail' => count($recs) . ' queue recommendation(s): ' . $recs[0]];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }

    private function validateCacheIsolation(): array
    {
        try {
            $result = $this->cacheIsolation->isCrossTenatAccess('tenant.1:key', 2);
            return $result
                ? ['status' => 'pass', 'detail' => 'Cross-tenant cache detection operational']
                : ['status' => 'fail', 'detail' => 'Cross-tenant cache detection not working'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }

    private function validateStorageIsolation(): array
    {
        try {
            $result = $this->attachmentIsolation->isCrossTenantAccess(1, 'tenants/2/file.pdf');
            return $result
                ? ['status' => 'pass', 'detail' => 'Storage cross-tenant detection operational']
                : ['status' => 'fail', 'detail' => 'Storage cross-tenant detection not working'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }

    private function validateSchedulingFairness(): array
    {
        try {
            $result = $this->scheduler->schedulerStatus();
            $deferred = $result['deferred_count'] ?? 0;
            $status = $deferred > 200 ? 'warn' : 'pass';
            return ['status' => $status, 'detail' => 'Deferred queue size: ' . $deferred];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }

    private function validatePressurePredictor(): array
    {
        try {
            $prediction = $this->pressurePredictor->predict();
            $level      = $prediction['pressure_level'] ?? 'unknown';
            $status     = in_array($level, ['critical', 'high'], true) ? 'warn' : 'pass';
            return ['status' => $status, 'detail' => 'Pressure predictor operational; level: ' . $level];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }

    private function validateNodeCoordination(): array
    {
        try {
            $status = $this->nodeCoordinator->clusterStatus();
            return ['status' => 'pass', 'detail' => 'Node coordination operational; nodes: ' . ($status['active_count'] ?? 0)];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => $e->getMessage()];
        }
    }
}
