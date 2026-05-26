<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Anomaly;

use Illuminate\Support\Facades\Cache;

/**
 * Detects signals that tenant isolation may have been breached.
 * Tracks cross-tenant access attempts via cache counters written by middleware/services.
 */
class TenantIsolationAnomalyDetector
{
    private const VIOLATION_CACHE_PREFIX  = 'telemetry.tenant_violation.';
    private const VIOLATION_THRESHOLD     = 3;

    public function detect(): array
    {
        $anomalies = [];
        $anomalies = array_merge($anomalies, $this->checkViolationCounters());
        return $anomalies;
    }

    public function recordViolationAttempt(int $tenantId, string $type = 'generic'): void
    {
        $key = self::VIOLATION_CACHE_PREFIX . $tenantId . '.' . $type;
        Cache::increment($key);
        Cache::put($key . '.ttl', 1, 3600);
    }

    public function getViolationCount(int $tenantId, string $type = 'generic'): int
    {
        return (int) Cache::get(self::VIOLATION_CACHE_PREFIX . $tenantId . '.' . $type, 0);
    }

    private function checkViolationCounters(): array
    {
        // Violation counters are written by other layers; we read aggregated signals here.
        // In production, integrate with a Redis SCAN or pre-aggregated metric.
        // This implementation reads from a known cache key set during auth boundary checks.
        $totalViolations = (int) Cache::get('telemetry.tenant_violations.total', 0);

        if ($totalViolations >= self::VIOLATION_THRESHOLD) {
            return [RuntimeAnomalyEngine::buildAnomaly(
                'TENANT_ISOLATION_VIOLATIONS',
                'critical',
                "Tenant isolation violation counter ({$totalViolations}) exceeds threshold",
                [
                    'violation_count' => $totalViolations,
                    'threshold'       => self::VIOLATION_THRESHOLD,
                ]
            )];
        }

        return [];
    }
}
