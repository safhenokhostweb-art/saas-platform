<?php

namespace SaaSPlatform\CoreUtils\StorageGovernance;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class TenantStorageGovernor
{
    public function __construct(
        private RuntimeStorageQuotaService $quotaService,
        private AttachmentIsolationManager $isolationManager,
        private AttachmentAccessTelemetry  $accessTelemetry,
        private StoragePressureAnalyzer    $pressureAnalyzer,
        private TelemetryManager           $telemetry
    ) {}

    public function authorizeUpload(int $tenantId, string $filename, float $sizeMb): array
    {
        if (!$this->isolationManager->isAllowedExtension($filename)) {
            return ['allowed' => false, 'reason' => 'extension_not_allowed'];
        }

        if ($this->quotaService->isExceeded($tenantId)) {
            $this->accessTelemetry->recordQuotaViolation($tenantId, $sizeMb, 0.0);
            return ['allowed' => false, 'reason' => 'quota_exceeded'];
        }

        $remaining = $this->quotaService->remainingMb($tenantId);
        if ($sizeMb > $remaining) {
            $this->accessTelemetry->recordQuotaViolation($tenantId, $sizeMb, $remaining);
            return ['allowed' => false, 'reason' => 'insufficient_quota', 'remaining_mb' => $remaining];
        }

        return ['allowed' => true, 'remaining_after_mb' => $remaining - $sizeMb];
    }

    public function recordUpload(int $tenantId, string $filename, float $sizeMb, string $context = ''): void
    {
        $this->quotaService->recordUsage($tenantId, $sizeMb);
        $this->accessTelemetry->recordUpload($tenantId, $filename, $sizeMb, $context);
    }

    public function authorizeAccess(int $requestingTenantId, string $path): array
    {
        $validation = $this->isolationManager->validatePath($requestingTenantId, $path);

        if (!$validation['valid']) {
            if ($validation['reason'] === 'cross_tenant_access') {
                $ownerTenantId = $this->isolationManager->extractTenantFromPath($path) ?? 0;
                $this->accessTelemetry->recordCrossTenantAttempt($requestingTenantId, $path, $ownerTenantId);
            }
            return ['allowed' => false, 'reason' => $validation['reason']];
        }

        return ['allowed' => true, 'sanitized_path' => $validation['sanitized_path']];
    }

    public function tenantStorageStatus(int $tenantId): array
    {
        return array_merge(
            $this->quotaService->stats($tenantId),
            ['pressure_level' => $this->pressureAnalyzer->pressureLevel($tenantId)]
        );
    }

    public function provisionForPlan(int $tenantId, string $plan): void
    {
        $this->quotaService->setQuotaForPlan($tenantId, $plan);
        $this->telemetry->info('storage', 'storage.tenant.provisioned', [
            'tenant_id' => $tenantId,
            'plan'      => $plan,
        ]);
    }
}
