<?php

namespace SaaSPlatform\CoreUtils\StorageGovernance;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;

class AttachmentAccessTelemetry
{
    public function __construct(private TelemetryManager $telemetry) {}

    public function recordUpload(int $tenantId, string $filename, float $sizeMb, string $context = ''): void
    {
        $this->telemetry->info('storage', 'storage.attachment.uploaded', [
            'tenant_id' => $tenantId,
            'filename'  => $filename,
            'size_mb'   => round($sizeMb, 3),
            'context'   => $context,
        ]);
    }

    public function recordDownload(int $tenantId, string $filename, string $accessedBy = ''): void
    {
        $this->telemetry->info('storage', 'storage.attachment.downloaded', [
            'tenant_id'   => $tenantId,
            'filename'    => $filename,
            'accessed_by' => $accessedBy,
        ]);
    }

    public function recordDeletion(int $tenantId, string $filename, float $freedMb, string $reason = ''): void
    {
        $this->telemetry->info('storage', 'storage.attachment.deleted', [
            'tenant_id' => $tenantId,
            'filename'  => $filename,
            'freed_mb'  => round($freedMb, 3),
            'reason'    => $reason,
        ]);
    }

    public function recordQuotaViolation(int $tenantId, float $requestedMb, float $remainingMb): void
    {
        $this->telemetry->warning('storage', 'storage.attachment.quota_violation', [
            'tenant_id'    => $tenantId,
            'requested_mb' => round($requestedMb, 3),
            'remaining_mb' => round($remainingMb, 3),
        ]);
    }

    public function recordCrossTenantAttempt(int $requestingTenantId, string $targetPath, int $owningTenantId): void
    {
        $this->telemetry->error('storage', 'storage.attachment.cross_tenant_attempt', [
            'requesting_tenant' => $requestingTenantId,
            'owning_tenant'     => $owningTenantId,
            'target_path'       => $targetPath,
        ]);
    }
}
