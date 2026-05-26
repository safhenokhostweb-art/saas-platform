<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Security;

use Illuminate\Support\Facades\Cache;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryContext;

/**
 * Tracks cross-tenant access attempts.
 * A violation occurs when request context tenant != attempted resource tenant.
 */
class TenantBoundaryViolationTelemetry
{
    private const VIOLATION_KEY = 'telemetry.tenant_violations.total';

    public function __construct(
        private TelemetryManager $telemetry,
        private TelemetryContext $context
    ) {}

    public function record(int $attemptedTenantId, string $resource = ''): void
    {
        $actualTenantId = $this->context->getTenantId();

        // Increment the global violation counter read by TenantIsolationAnomalyDetector
        Cache::increment(self::VIOLATION_KEY);

        $this->telemetry->critical('security', 'security.tenant.boundary_violation', [
            'actual_tenant_id'   => $actualTenantId,
            'attempted_tenant_id' => $attemptedTenantId,
            'resource'           => $resource,
            'trace_id'           => $this->context->getTraceId(),
            'request_id'         => $this->context->getRequestId(),
        ]);
    }

    public function totalViolationCount(): int
    {
        return (int) Cache::get(self::VIOLATION_KEY, 0);
    }

    public function resetCounter(): void
    {
        Cache::forget(self::VIOLATION_KEY);
    }
}
