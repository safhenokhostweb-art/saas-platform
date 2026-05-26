<?php

namespace SaaSPlatform\CoreUtils\RuntimeIsolation;

use SaaSPlatform\CoreUtils\RuntimeGovernance\Throttle\RuntimeThrottleManager;
use SaaSPlatform\CoreUtils\RuntimeGovernance\Degradation\TenantProtectionCoordinator;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class TenantResourceGovernor
{
    private const THROTTLE_FACTOR_NEAR   = 0.5;
    private const THROTTLE_FACTOR_AT     = 0.1;

    public function __construct(
        private TenantQuotaManager          $quotaManager,
        private TenantRuntimeBudget         $budget,
        private TenantConsumptionTracker    $tracker,
        private RuntimeThrottleManager      $throttleManager,
        private TenantProtectionCoordinator $protectionCoordinator,
        private TelemetryManager            $telemetry
    ) {}

    public function enforce(int $tenantId): EnforcementResult
    {
        $quota   = $this->quotaManager->getQuota($tenantId);
        $summary = $this->tracker->consumptionSummary($tenantId);

        $exhausted = array_filter($summary, fn($r) => $r['exhausted']);
        $nearLimit = array_filter($summary, fn($r) => $r['utilization'] >= 80 && !$r['exhausted']);

        if (!empty($exhausted)) {
            return $this->enforceExhaustion($tenantId, array_keys($exhausted));
        }

        if (!empty($nearLimit)) {
            return $this->enforceNearLimit($tenantId, array_keys($nearLimit));
        }

        return EnforcementResult::allow();
    }

    public function allow(int $tenantId, string $resource): bool
    {
        $quota   = $this->quotaManager->getQuota($tenantId);
        $ceiling = $quota[$resource] ?? 0;
        return $ceiling <= 0 || !$this->budget->isExhausted($tenantId, $resource, $ceiling);
    }

    public function tenantStatus(int $tenantId): array
    {
        return [
            'tenant_id'    => $tenantId,
            'plan'         => $this->quotaManager->getPlan($tenantId),
            'priority'     => $this->quotaManager->getPriority($tenantId),
            'consumption'  => $this->tracker->consumptionSummary($tenantId),
            'throttleable' => $this->tracker->isThrottleable($tenantId),
            'emergency'    => $this->quotaManager->isUnderEmergencyQuota($tenantId),
        ];
    }

    private function enforceExhaustion(int $tenantId, array $resources): EnforcementResult
    {
        $this->throttleManager->activateTenant($tenantId, self::THROTTLE_FACTOR_AT, 'quota_exhausted:' . implode(',', $resources));

        $this->telemetry->error('isolation', 'isolation.tenant.quota_exhausted', [
            'tenant_id' => $tenantId,
            'resources' => $resources,
        ]);

        return EnforcementResult::throttled(self::THROTTLE_FACTOR_AT, 'quota_exhausted', $resources);
    }

    private function enforceNearLimit(int $tenantId, array $resources): EnforcementResult
    {
        $this->throttleManager->activateTenant($tenantId, self::THROTTLE_FACTOR_NEAR, 'near_quota:' . implode(',', $resources));

        return EnforcementResult::throttled(self::THROTTLE_FACTOR_NEAR, 'near_quota', $resources);
    }
}
