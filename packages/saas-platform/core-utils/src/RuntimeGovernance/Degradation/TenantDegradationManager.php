<?php

namespace SaaSPlatform\CoreUtils\RuntimeGovernance\Degradation;

use SaaSPlatform\CoreUtils\Telemetry\TelemetryManager;
use SaaSPlatform\CoreUtils\RuntimeGovernance\GovernanceContext;

class TenantDegradationManager
{
    // Degradation levels:
    // 0 = Normal
    // 1 = Reduced telemetry verbosity
    // 2 = Disable heavy reports
    // 3 = Queue-only notifications
    // 4 = Read-only mode
    // 5 = Tenant isolation mode

    public function __construct(
        private GovernanceContext $context,
        private TelemetryManager  $telemetry
    ) {}

    public function degrade(int $tenantId, int $level, string $reason = 'governance_policy'): void
    {
        $current = $this->context->tenantDegradationLevel($tenantId);
        $level   = max(0, min(5, $level));

        if ($level === $current) {
            return;
        }

        $this->context->setTenantDegradationLevel($tenantId, $level);

        $this->telemetry->emit(
            'governance',
            $level >= 4 ? TelemetryManager::SEVERITY_CRITICAL : TelemetryManager::SEVERITY_WARNING,
            'governance.tenant.degraded',
            [
                'tenant_id'        => $tenantId,
                'previous_level'   => $current,
                'new_level'        => $level,
                'level_name'       => self::levelName($level),
                'reason'           => $reason,
                // Billing and admin access are ALWAYS preserved — see TenantIsolationPolicy
            ]
        );
    }

    public function restore(int $tenantId, string $reason = 'recovery'): void
    {
        $current = $this->context->tenantDegradationLevel($tenantId);

        if ($current === 0) {
            return;
        }

        $this->context->setTenantDegradationLevel($tenantId, 0);

        $this->telemetry->info('governance', 'governance.tenant.restored', [
            'tenant_id'      => $tenantId,
            'previous_level' => $current,
            'reason'         => $reason,
        ]);
    }

    public function currentLevel(int $tenantId): int
    {
        return $this->context->tenantDegradationLevel($tenantId);
    }

    public function levelName(int $level): string
    {
        return match ($level) {
            0 => 'normal',
            1 => 'reduced_telemetry',
            2 => 'reports_disabled',
            3 => 'queue_only_notifications',
            4 => 'read_only',
            5 => 'isolated',
            default => 'unknown',
        };
    }

    public function isReadOnly(int $tenantId): bool
    {
        return $this->currentLevel($tenantId) >= 4;
    }

    public function isIsolated(int $tenantId): bool
    {
        return $this->currentLevel($tenantId) >= 5;
    }

    public function isDegraded(int $tenantId): bool
    {
        return $this->currentLevel($tenantId) > 0;
    }

    public function capabilities(int $tenantId): array
    {
        $level = $this->currentLevel($tenantId);

        return [
            'telemetry'        => $level < 1,
            'heavy_reports'    => $level < 2,
            'notifications'    => $level < 3,
            'write_operations' => $level < 4,
            'all_operations'   => $level < 5,
            // Always preserved regardless of level:
            'billing_access'   => true,
            'admin_access'     => true,
        ];
    }
}
