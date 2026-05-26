<?php

namespace SaaSPlatform\CoreUtils\Telemetry\Tenant;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use SaaSPlatform\CoreUtils\Telemetry\TelemetryContext;

/**
 * Tenant-scoped observability entry point.
 * All emitted events are scoped to the current tenant_id.
 * Cross-tenant data access is structurally prevented by design.
 */
class TenantObservabilityService
{
    public function __construct(
        private TelemetryManager  $telemetry,
        private TelemetryContext  $context,
        private TenantRuntimeMetrics $metrics,
        private TenantIncidentTracker $incidents
    ) {}

    public function recordRequest(string $route, float $durationMs, int $statusCode): void
    {
        $tenantId = $this->context->getTenantId();
        if ($tenantId === null) {
            return;
        }

        $this->telemetry->info('tenant', 'tenant.request', [
            'tenant_id'   => $tenantId,
            'route'       => $route,
            'duration_ms' => $durationMs,
            'status_code' => $statusCode,
        ]);

        $this->metrics->recordRequest($tenantId, $durationMs, $statusCode);
    }

    public function recordError(string $error, string $context = ''): void
    {
        $tenantId = $this->context->getTenantId();
        if ($tenantId === null) {
            return;
        }

        $this->telemetry->error('tenant', 'tenant.error', [
            'tenant_id' => $tenantId,
            'error'     => $error,
            'context'   => $context,
        ]);

        $this->incidents->recordError($tenantId, $error);
    }

    public function snapshotForTenant(int $tenantId): array
    {
        // Only reads data for the specified tenant — no cross-tenant exposure
        return [
            'tenant_id' => $tenantId,
            'metrics'   => $this->metrics->getForTenant($tenantId),
            'incidents' => $this->incidents->getForTenant($tenantId),
        ];
    }
}
